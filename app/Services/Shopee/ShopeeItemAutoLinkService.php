<?php

namespace App\Services\Shopee;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ShopeeItemLinkAttempt;
use App\Models\ShopeeItemLinkRunner;
use App\Models\Shopeesync;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShopeeItemAutoLinkService
{
    public const MAX_ATTEMPTS = 21;

    public const RETRY_SPACING_HOURS = 24;

    public const RETRY_CAMPAIGN_DAYS = 21;

    public const ROLLING_WINDOW_SIZE = 5000;

    public const HOURLY_CALL_CAP = 200;

    /** Unlinked SKUs attempted per cron tick (stops early on hourly API cap). */
    public const DEFAULT_BATCH_PER_RUN = 50;

    /** Max Shopee item ids to fetch model lists for when search has no item_sku hit. */
    public const MODEL_SCAN_LIMIT = 10;

    protected ?int $cachedRollingMinId = null;

    public function __construct(
        protected ShopeeStockApiService $stockApi,
    ) {}

    public function runner(): ShopeeItemLinkRunner
    {
        return ShopeeItemLinkRunner::state();
    }

    public function pause(): void
    {
        $this->runner()->update(['paused' => true]);
    }

    public function resume(): void
    {
        $this->runner()->update(['paused' => false]);
    }

    /**
     * @return array{processed: int, linked: int, remaining_budget: int}
     */
    public function processBatch(int $maxItems): array
    {
        $runner = $this->runner();
        if ($runner->paused) {
            return ['processed' => 0, 'linked' => 0, 'remaining_budget' => $this->remainingHourlyBudget()];
        }

        $linked = 0;
        $processed = 0;
        $budget = $this->remainingHourlyBudget();

        while ($processed < $maxItems && $budget > 0) {
            $item = $this->pickNextItem();
            if ($item === null) {
                break;
            }

            $result = $this->discoverForItem($item);
            $processed++;
            if ($result['outcome'] === ShopeeItemLinkAttempt::OUTCOME_LINKED) {
                $linked++;
            }

            if ($result['counted_api_call'] > 0) {
                $budget = $this->remainingHourlyBudget();
            }
        }

        $runner->update(['last_run_at' => now()]);

        return [
            'processed' => $processed,
            'linked' => $linked,
            'remaining_budget' => $this->remainingHourlyBudget(),
        ];
    }

    /**
     * @return array{outcome: string, counted_api_call: int, message: string}
     */
    public function discoverForItem(Item $item): array
    {
        if ((int) ($item->shopee_item_id ?? 0) > 0) {
            return $this->skipped('Already linked');
        }

        if (! $this->stockApi->isReady()) {
            return $this->skipped('Shopee STOCK CHECKER not authorized');
        }

        if (! $this->itemIsEligible($item)) {
            return $this->skipped('Not eligible');
        }

        if ($this->isExhausted($item->id)) {
            return $this->skipped('Attempt cap reached');
        }

        if (! $this->dueForRetry($item->id)) {
            return $this->skipped('Retry spacing');
        }

        if ($this->remainingHourlyBudget() <= 0) {
            return $this->skipped('Hourly cap');
        }

        $searchQ = $this->resolveSearchQuery($item);

        $discovery = $this->findExactSkuMatches($searchQ);

        $this->incrementHourlyCalls($discovery['api_calls']);
        $matches = $discovery['matches'];
        $candidates = $discovery['candidates'];

        if (count($matches) === 1) {
            $match = $matches[0];
            $shopeeItemId = (int) $match['item_id'];
            $shopeeModelId = (int) ($match['model_id'] ?? 0);

            if ($shopeeItemId > 0) {
                $item->update([
                    'shopee_item_id' => $shopeeItemId,
                    'shopee_model_id' => $shopeeModelId > 0 ? $shopeeModelId : null,
                ]);

                return $this->recordAttempt(
                    $item,
                    ShopeeItemLinkAttempt::OUTCOME_LINKED,
                    $searchQ,
                    $shopeeItemId,
                    $shopeeModelId > 0 ? $shopeeModelId : null,
                    $candidates,
                    null,
                    null,
                    $discovery['api_calls'],
                );
            }
        }

        if (count($matches) > 1) {
            return $this->recordAttempt(
                $item,
                ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS,
                $searchQ,
                null,
                null,
                count($matches),
                null,
                'Multiple exact SKU matches',
                $discovery['api_calls'],
            );
        }

        return $this->recordAttempt(
            $item,
            ShopeeItemLinkAttempt::OUTCOME_NO_MATCH,
            $searchQ,
            null,
            null,
            $candidates,
            null,
            null,
            $discovery['api_calls'],
        );
    }

    /**
     * @return array{matches: list<array{item_id: int, model_id: int}>, api_calls: int, candidates: int}
     */
    public function findExactSkuMatches(string $searchQ): array
    {
        $needle = strtoupper(trim($searchQ));
        $apiCalls = 0;

        $rows = $this->stockApi->discoverCandidatesForSku($searchQ, 50);
        $apiCalls += $this->estimateNameDiscoverApiCalls($searchQ, $rows !== []);

        $matches = [];

        $itemSkuHits = collect($rows)->filter(
            fn (array $row) => strtoupper(trim((string) ($row['item_sku'] ?? $row['sku'] ?? ''))) === $needle
        );

        foreach ($itemSkuHits as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $matches = array_merge($matches, $this->exactMatchesForShopeeItem($itemId, $needle, $apiCalls));
        }

        if ($matches === []) {
            $scanned = 0;
            foreach ($rows as $row) {
                if ($scanned >= self::MODEL_SCAN_LIMIT) {
                    break;
                }
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $scanned++;
                $matches = array_merge($matches, $this->exactModelSkuMatches($itemId, $needle, $apiCalls));
            }
        }

        if ($matches === [] && $rows === []) {
            $runner = $this->runner();
            $catalog = $this->stockApi->findExactModelSkuOnCatalogPage(
                $searchQ,
                (int) ($runner->catalog_scan_offset ?? 0),
            );
            $apiCalls += $catalog['api_calls'];
            $runner->update([
                'catalog_scan_offset' => $catalog['next_offset'] !== null ? $catalog['next_offset'] : 0,
            ]);
            $matches = $catalog['matches'];
        }

        return [
            'matches' => $this->uniqueMatches($matches),
            'api_calls' => $apiCalls,
            'candidates' => count($rows) > 0 ? count($rows) : count($matches),
        ];
    }

    protected function estimateNameDiscoverApiCalls(string $searchQ, bool $hydrated): int
    {
        $searches = count($this->stockApi->itemNameSearchQueries($searchQ));

        return $searches + ($hydrated ? 1 : 0);
    }

    /**
     * @return list<array{item_id: int, model_id: int}>
     */
    protected function exactMatchesForShopeeItem(int $itemId, string $needle, int &$apiCalls): array
    {
        $models = $this->stockApi->modelsForItem($itemId);
        $apiCalls++;

        if ($models === []) {
            return [['item_id' => $itemId, 'model_id' => 0]];
        }

        $modelHits = $this->filterExactModelSku($models, $needle);

        if (count($modelHits) === 1) {
            return [['item_id' => $itemId, 'model_id' => (int) ($modelHits[0]['model_id'] ?? 0)]];
        }

        if (count($modelHits) > 1) {
            return array_map(
                fn (array $m) => ['item_id' => $itemId, 'model_id' => (int) ($m['model_id'] ?? 0)],
                $modelHits,
            );
        }

        return [];
    }

    /**
     * @return list<array{item_id: int, model_id: int}>
     */
    protected function exactModelSkuMatches(int $itemId, string $needle, int &$apiCalls): array
    {
        $models = $this->stockApi->modelsForItem($itemId);
        $apiCalls++;

        $modelHits = $this->filterExactModelSku($models, $needle);

        return array_map(
            fn (array $m) => ['item_id' => $itemId, 'model_id' => (int) ($m['model_id'] ?? 0)],
            $modelHits,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return list<array<string, mixed>>
     */
    protected function filterExactModelSku(array $models, string $needle): array
    {
        return array_values(array_filter(
            $models,
            fn (array $model) => strtoupper(trim((string) ($model['model_sku'] ?? ''))) === $needle,
        ));
    }

    /**
     * @param  list<array{item_id: int, model_id: int}>  $matches
     * @return list<array{item_id: int, model_id: int}>
     */
    protected function uniqueMatches(array $matches): array
    {
        $unique = [];
        foreach ($matches as $match) {
            $key = $match['item_id'].'-'.$match['model_id'];
            $unique[$key] = $match;
        }

        return array_values($unique);
    }

    public function resolveSearchQuery(Item $item): string
    {
        $code = trim((string) $item->code);
        $legacy = trim((string) ($item->legacy_code ?? ''));
        $inRollingWindow = (int) $item->id >= $this->rollingWindowMinItemId();

        if ($inRollingWindow && $legacy !== '' && strcasecmp($legacy, $code) !== 0) {
            $last = $this->lastAttempt($item->id);
            if ($last !== null
                && strcasecmp($last->search_q, $legacy) === 0
                && $last->outcome !== ShopeeItemLinkAttempt::OUTCOME_LINKED) {
                return $code;
            }

            return $legacy;
        }

        return $code;
    }

    public function itemIsEligible(Item $item): bool
    {
        if ($item->deleted_at !== null) {
            return false;
        }

        $type = ItemType::coerce($item->type);
        if ($type !== ItemType::ITEM && $type !== ItemType::ASSET_LANCAR) {
            return false;
        }

        if (! $this->itemIdInAutoLinkScope((int) $item->id, $item->created_at)) {
            return false;
        }

        return $this->hasStockInMappedWarehouses((int) $item->id);
    }

    public function hasStockInMappedWarehouses(int $itemId): bool
    {
        $warehouseIds = $this->mappedWarehouseIds();
        if ($warehouseIds === []) {
            return false;
        }

        return WarehouseItem::query()
            ->where('item_id', $itemId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->forAvailableStock()
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function mappedWarehouseIds(): array
    {
        return Shopeesync::query()
            ->where('warehouse_id', '>', 0)
            ->distinct()
            ->pluck('warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isExhausted(int $itemId): bool
    {
        return $this->failedAttemptCount($itemId) >= self::MAX_ATTEMPTS;
    }

    public function failedAttemptCount(int $itemId): int
    {
        return (int) ShopeeItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->whereIn('outcome', [
                ShopeeItemLinkAttempt::OUTCOME_NO_MATCH,
                ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS,
                ShopeeItemLinkAttempt::OUTCOME_API_ERROR,
            ])
            ->count();
    }

    public function dueForRetry(int $itemId): bool
    {
        $last = $this->lastMeaningfulAttempt($itemId);
        if ($last === null) {
            return true;
        }

        return $last->created_at->lte(now()->subHours(self::RETRY_SPACING_HOURS));
    }

    public function itemIdInAutoLinkScope(int $itemId, mixed $createdAt): bool
    {
        $minId = $this->rollingWindowMinItemId();
        if ($minId > 0 && $itemId >= $minId) {
            return true;
        }

        if ($createdAt === null) {
            return false;
        }

        return \Illuminate\Support\Carbon::parse($createdAt)->gte(now()->subDays(self::RETRY_CAMPAIGN_DAYS));
    }

    public function lastAttempt(int $itemId): ?ShopeeItemLinkAttempt
    {
        return ShopeeItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->latest('id')
            ->first();
    }

    public function lastMeaningfulAttempt(int $itemId): ?ShopeeItemLinkAttempt
    {
        return ShopeeItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->where('outcome', '!=', ShopeeItemLinkAttempt::OUTCOME_SKIPPED)
            ->latest('id')
            ->first();
    }

    public function pickNextItem(): ?Item
    {
        $zeroRetry = $this->eligibleUnlinkedQuery()
            ->where('shopee_item_id', 0)
            ->orderByDesc('id')
            ->first();

        if ($zeroRetry !== null) {
            return $zeroRetry;
        }

        return $this->eligibleUnlinkedQuery()
            ->whereNull('shopee_item_id')
            ->orderByDesc('id')
            ->first();
    }

    public function rollingWindowMinItemId(): int
    {
        if ($this->cachedRollingMinId !== null) {
            return $this->cachedRollingMinId;
        }

        $maxId = (int) Item::query()
            ->whereNull('deleted_at')
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->max('id');

        if ($maxId <= 0) {
            $this->cachedRollingMinId = 0;

            return 0;
        }

        $this->cachedRollingMinId = max(1, $maxId - self::ROLLING_WINDOW_SIZE + 1);

        return $this->cachedRollingMinId;
    }

    protected function eligibleBaseQuery(): Builder
    {
        $warehouseIds = $this->mappedWarehouseIds();
        $minId = $this->rollingWindowMinItemId();
        $campaignStart = now()->subDays(self::RETRY_CAMPAIGN_DAYS);

        return Item::query()
            ->whereNull('deleted_at')
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->where(function (Builder $query) use ($minId, $campaignStart) {
                if ($minId > 0) {
                    $query->where('items.id', '>=', $minId);
                }
                $query->orWhere('items.created_at', '>=', $campaignStart);
            })
            ->when($warehouseIds !== [], function (Builder $query) use ($warehouseIds) {
                $query->whereExists(function ($sub) use ($warehouseIds) {
                    $sub->select(DB::raw(1))
                        ->from('warehouse_item')
                        ->join('customers', 'customers.id', '=', 'warehouse_item.warehouse_id')
                        ->whereColumn('warehouse_item.item_id', 'items.id')
                        ->whereIn('warehouse_item.warehouse_id', $warehouseIds)
                        ->where('warehouse_item.quantity', '>', 0)
                        ->where('customers.type', 2)
                        ->whereNull('customers.deleted_at');
                });
            }, fn (Builder $query) => $query->whereRaw('0 = 1'));
    }

    protected function eligibleUnlinkedQuery(): Builder
    {
        return $this->eligibleBaseQuery()
            ->where(function (Builder $query) {
                $query->whereNull('shopee_item_id')
                    ->orWhere('shopee_item_id', '<=', 0);
            })
            ->where(function (Builder $query) {
                $query->whereRaw(
                    '(select count(*) from shopee_item_link_attempts where shopee_item_link_attempts.item_id = items.id and outcome in (?, ?, ?)) < ?',
                    [
                        ShopeeItemLinkAttempt::OUTCOME_NO_MATCH,
                        ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS,
                        ShopeeItemLinkAttempt::OUTCOME_API_ERROR,
                        self::MAX_ATTEMPTS,
                    ],
                );
            })
            ->where(function (Builder $query) {
                $query->whereDoesntHave('shopeeLinkAttempts', function (Builder $inner) {
                    $inner->where('outcome', '!=', ShopeeItemLinkAttempt::OUTCOME_SKIPPED)
                        ->where('created_at', '>', now()->subHours(self::RETRY_SPACING_HOURS));
                });
            });
    }

    public function remainingHourlyBudget(): int
    {
        return max(0, self::HOURLY_CALL_CAP - $this->hourlyCallsUsed());
    }

    public function hourlyCallsUsed(): int
    {
        $runner = $this->runner();
        $bucket = $this->hourlyBucket();

        if ($runner->calls_hour_bucket !== $bucket) {
            return 0;
        }

        return (int) $runner->calls_hour_count;
    }

    protected function incrementHourlyCalls(int $count = 1): void
    {
        if ($count <= 0) {
            return;
        }

        $runner = $this->runner();
        $bucket = $this->hourlyBucket();

        if ($runner->calls_hour_bucket !== $bucket) {
            $runner->update([
                'calls_hour_bucket' => $bucket,
                'calls_hour_count' => $count,
            ]);

            return;
        }

        $runner->increment('calls_hour_count', $count);
    }

    protected function hourlyBucket(): string
    {
        return now()->format('Y-m-d-H');
    }

    /**
     * @return array{outcome: string, counted_api_call: int, message: string}
     */
    protected function skipped(string $message): array
    {
        return [
            'outcome' => ShopeeItemLinkAttempt::OUTCOME_SKIPPED,
            'counted_api_call' => 0,
            'message' => $message,
        ];
    }

    protected function recordAttempt(
        Item $item,
        string $outcome,
        string $searchQ,
        ?int $matchedItemId,
        ?int $matchedModelId,
        int $candidates,
        ?int $httpStatus,
        ?string $error,
        int $apiCalls,
    ): array {
        ShopeeItemLinkAttempt::query()->create([
            'item_id' => $item->id,
            'outcome' => $outcome,
            'search_q' => $searchQ,
            'matched_shopee_item_id' => $matchedItemId,
            'matched_shopee_model_id' => $matchedModelId,
            'candidates_count' => $candidates,
            'http_status' => $httpStatus,
            'error_message' => $error,
        ]);

        return [
            'outcome' => $outcome,
            'counted_api_call' => $apiCalls,
            'message' => $error ?? $outcome,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function dashboardStats(): array
    {
        $today = now()->startOfDay();

        return [
            'calls_this_hour' => $this->hourlyCallsUsed(),
            'calls_cap' => self::HOURLY_CALL_CAP,
            'linked_today' => ShopeeItemLinkAttempt::query()->where('outcome', ShopeeItemLinkAttempt::OUTCOME_LINKED)->where('created_at', '>=', $today)->count(),
            'no_match_today' => ShopeeItemLinkAttempt::query()->where('outcome', ShopeeItemLinkAttempt::OUTCOME_NO_MATCH)->where('created_at', '>=', $today)->count(),
            'ambiguous_today' => ShopeeItemLinkAttempt::query()->where('outcome', ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS)->where('created_at', '>=', $today)->count(),
            'api_error_today' => ShopeeItemLinkAttempt::query()->where('outcome', ShopeeItemLinkAttempt::OUTCOME_API_ERROR)->where('created_at', '>=', $today)->count(),
            'rolling_window_size' => self::ROLLING_WINDOW_SIZE,
            'rolling_window_min_id' => $this->rollingWindowMinItemId(),
            'max_attempts' => self::MAX_ATTEMPTS,
            'retry_spacing_hours' => self::RETRY_SPACING_HOURS,
            'retry_campaign_days' => self::RETRY_CAMPAIGN_DAYS,
            'eligible_in_window' => $this->eligibleUnlinkedQuery()->count(),
            'zero_retry_due' => $this->eligibleUnlinkedQuery()->where('shopee_item_id', 0)->count(),
        ];
    }

    /**
     * @return Collection<int, ShopeeItemLinkAttempt>
     */
    public function recentAttempts(int $limit = 50): Collection
    {
        return ShopeeItemLinkAttempt::query()
            ->with(['item:id,code,name'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function resetAttemptsForItem(int $itemId): void
    {
        ShopeeItemLinkAttempt::query()->where('item_id', $itemId)->delete();
    }
}
