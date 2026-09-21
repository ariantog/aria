<?php

namespace App\Services\Jubelio;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\JubelioItemLinkAttempt;
use App\Models\JubelioItemLinkRunner;
use App\Models\Jubeliosync;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JubelioItemAutoLinkService
{
    public const TO_STOCK_URL = 'https://api2.jubelio.com/inventory/items/to-stock/';

    public const MAX_ATTEMPTS = 5;

    public const RETRY_SPACING_HOURS = 24;

    public const MIN_ITEM_AGE_HOURS = 24;

    public const RECENT_MAX_DAYS = 30;

    public const HOURLY_CALL_CAP = 200;

    public const DEFAULT_BATCH_PER_RUN = 4;

    public function __construct(
        protected JubelioService $jubelio,
    ) {}

    public function runner(): JubelioItemLinkRunner
    {
        return JubelioItemLinkRunner::state();
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

        config(['services.jubelio.active' => true]);

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
            if ($result['outcome'] === JubelioItemLinkAttempt::OUTCOME_LINKED) {
                $linked++;
            }

            if ($result['counted_api_call']) {
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
     * @return array{outcome: string, counted_api_call: bool, message: string}
     */
    public function discoverForItem(Item $item): array
    {
        if ((int) ($item->jubelio_item_id ?? 0) > 0) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Already linked', false);
        }

        if (! $this->itemIsEligible($item)) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Not eligible', false);
        }

        $storedJubelioId = (int) ($item->jubelio_item_id ?? 0);

        if ($storedJubelioId === 0 && $item->jubelio_item_id !== null) {
            if (! $this->dueForZeroRetry($item->id)) {
                return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Zero-id retry not due', false);
            }
        } elseif ($this->isExhausted($item->id)) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Attempt cap reached', false);
        } elseif (! $this->dueForRetry($item->id)) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Retry spacing', false);
        }

        if ($this->remainingHourlyBudget() <= 0) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_SKIPPED, '', null, 0, null, 'Hourly cap', false);
        }

        $searchQ = $this->resolveSearchQuery($item);
        $this->incrementHourlyCalls();

        $response = $this->jubelio->get(self::TO_STOCK_URL, ['q' => $searchQ]);

        if ($response === null) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_API_ERROR, $searchQ, null, 0, null, 'Authentication failed', true);
        }

        if (! $response->successful()) {
            return $this->recordAttempt(
                $item,
                JubelioItemLinkAttempt::OUTCOME_API_ERROR,
                $searchQ,
                null,
                0,
                $response->status(),
                'HTTP '.$response->status(),
                true,
            );
        }

        $payload = $response->json();
        $rows = collect($payload['data'] ?? []);
        $needle = strtoupper(trim($searchQ));
        $exact = $rows->filter(
            fn (array $row) => strtoupper(trim((string) ($row['item_code'] ?? ''))) === $needle
        )->values();

        if ($exact->count() === 1) {
            $match = $exact->first();
            $jubelioId = (int) ($match['item_id'] ?? 0);
            if ($jubelioId > 0) {
                $item->update(['jubelio_item_id' => $jubelioId]);

                return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_LINKED, $searchQ, $jubelioId, $rows->count(), $response->status(), null, true);
            }
        }

        if ($exact->count() > 1) {
            return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS, $searchQ, null, $exact->count(), $response->status(), 'Multiple exact code matches', true);
        }

        return $this->recordAttempt($item, JubelioItemLinkAttempt::OUTCOME_NO_MATCH, $searchQ, null, $rows->count(), $response->status(), null, true);
    }

    public function resolveSearchQuery(Item $item): string
    {
        $code = trim((string) $item->code);
        $legacy = trim((string) ($item->legacy_code ?? ''));
        $isRecent = $item->created_at instanceof CarbonInterface
            && $item->created_at->gte(now()->subDays(self::RECENT_MAX_DAYS));

        if ($isRecent && $legacy !== '' && strcasecmp($legacy, $code) !== 0) {
            $last = $this->lastAttempt($item->id);
            if ($last !== null
                && strcasecmp($last->search_q, $legacy) === 0
                && $last->outcome !== JubelioItemLinkAttempt::OUTCOME_LINKED) {
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

        if ($item->created_at === null || $item->created_at->gt(now()->subHours(self::MIN_ITEM_AGE_HOURS))) {
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
        return Jubeliosync::query()
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
        return (int) JubelioItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->whereIn('outcome', [
                JubelioItemLinkAttempt::OUTCOME_NO_MATCH,
                JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS,
                JubelioItemLinkAttempt::OUTCOME_API_ERROR,
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

    public function dueForZeroRetry(int $itemId): bool
    {
        $last = $this->lastMeaningfulAttempt($itemId);

        return $last === null || $last->created_at->lte(now()->subDay());
    }

    public function lastAttempt(int $itemId): ?JubelioItemLinkAttempt
    {
        return JubelioItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->latest('id')
            ->first();
    }

    public function lastMeaningfulAttempt(int $itemId): ?JubelioItemLinkAttempt
    {
        return JubelioItemLinkAttempt::query()
            ->where('item_id', $itemId)
            ->where('outcome', '!=', JubelioItemLinkAttempt::OUTCOME_SKIPPED)
            ->latest('id')
            ->first();
    }

    public function pickNextItem(): ?Item
    {
        $zeroRetry = $this->eligibleBaseQuery()
            ->where('jubelio_item_id', 0)
            ->where(function (Builder $query) {
                $query->whereDoesntHave('jubelioLinkAttempts', function (Builder $inner) {
                    $inner->where('created_at', '>=', now()->subDay())
                        ->where('outcome', '!=', JubelioItemLinkAttempt::OUTCOME_SKIPPED);
                });
            })
            ->orderBy('id')
            ->first();

        if ($zeroRetry !== null) {
            return $zeroRetry;
        }

        $recent = $this->eligibleUnlinkedQuery()
            ->where('created_at', '>=', now()->subDays(self::RECENT_MAX_DAYS))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recent !== null) {
            return $recent;
        }

        return $this->eligibleUnlinkedQuery()
            ->where('created_at', '<', now()->subDays(self::RECENT_MAX_DAYS))
            ->orderBy('id')
            ->first();
    }

    protected function eligibleBaseQuery(): Builder
    {
        $warehouseIds = $this->mappedWarehouseIds();

        return Item::query()
            ->whereNull('deleted_at')
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->where('created_at', '<=', now()->subHours(self::MIN_ITEM_AGE_HOURS))
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
            ->whereNull('jubelio_item_id')
            ->where(function (Builder $query) {
                $query->whereRaw(
                    '(select count(*) from jubelio_item_link_attempts where jubelio_item_link_attempts.item_id = items.id and outcome in (?, ?, ?)) < ?',
                    [
                        JubelioItemLinkAttempt::OUTCOME_NO_MATCH,
                        JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS,
                        JubelioItemLinkAttempt::OUTCOME_API_ERROR,
                        self::MAX_ATTEMPTS,
                    ],
                );
            })
            ->where(function (Builder $query) {
                $query->whereDoesntHave('jubelioLinkAttempts', function (Builder $inner) {
                    $inner->where('outcome', '!=', JubelioItemLinkAttempt::OUTCOME_SKIPPED)
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

    protected function incrementHourlyCalls(): void
    {
        $runner = $this->runner();
        $bucket = $this->hourlyBucket();

        if ($runner->calls_hour_bucket !== $bucket) {
            $runner->update([
                'calls_hour_bucket' => $bucket,
                'calls_hour_count' => 1,
            ]);

            return;
        }

        $runner->increment('calls_hour_count');
    }

    protected function hourlyBucket(): string
    {
        return now()->format('Y-m-d-H');
    }

    /**
     * @return array{outcome: string, counted_api_call: bool, message: string}
     */
    protected function recordAttempt(
        Item $item,
        string $outcome,
        string $searchQ,
        ?int $matchedId,
        int $candidates,
        ?int $httpStatus,
        ?string $error,
        bool $countedApiCall,
    ): array {
        JubelioItemLinkAttempt::query()->create([
            'item_id' => $item->id,
            'outcome' => $outcome,
            'search_q' => $searchQ,
            'matched_jubelio_item_id' => $matchedId,
            'candidates_count' => $candidates,
            'http_status' => $httpStatus,
            'error_message' => $error,
        ]);

        return [
            'outcome' => $outcome,
            'counted_api_call' => $countedApiCall,
            'message' => $error ?? $outcome,
        ];
    }

    /**
     * @return array{
     *     calls_this_hour: int,
     *     calls_cap: int,
     *     linked_today: int,
     *     no_match_today: int,
     *     ambiguous_today: int,
     *     api_error_today: int,
     *     eligible_recent: int,
     *     eligible_rolling: int,
     *     zero_retry_due: int,
     * }
     */
    public function dashboardStats(): array
    {
        $today = now()->startOfDay();

        return [
            'calls_this_hour' => $this->hourlyCallsUsed(),
            'calls_cap' => self::HOURLY_CALL_CAP,
            'linked_today' => JubelioItemLinkAttempt::query()->where('outcome', JubelioItemLinkAttempt::OUTCOME_LINKED)->where('created_at', '>=', $today)->count(),
            'no_match_today' => JubelioItemLinkAttempt::query()->where('outcome', JubelioItemLinkAttempt::OUTCOME_NO_MATCH)->where('created_at', '>=', $today)->count(),
            'ambiguous_today' => JubelioItemLinkAttempt::query()->where('outcome', JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS)->where('created_at', '>=', $today)->count(),
            'api_error_today' => JubelioItemLinkAttempt::query()->where('outcome', JubelioItemLinkAttempt::OUTCOME_API_ERROR)->where('created_at', '>=', $today)->count(),
            'eligible_recent' => $this->eligibleUnlinkedQuery()->where('created_at', '>=', now()->subDays(self::RECENT_MAX_DAYS))->count(),
            'eligible_rolling' => $this->eligibleUnlinkedQuery()->where('created_at', '<', now()->subDays(self::RECENT_MAX_DAYS))->count(),
            'zero_retry_due' => $this->eligibleBaseQuery()->where('jubelio_item_id', 0)->count(),
        ];
    }

    /**
     * @return Collection<int, JubelioItemLinkAttempt>
     */
    public function recentAttempts(int $limit = 50): Collection
    {
        return JubelioItemLinkAttempt::query()
            ->with(['item:id,code,name'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function resetAttemptsForItem(int $itemId): void
    {
        JubelioItemLinkAttempt::query()->where('item_id', $itemId)->delete();
    }
}
