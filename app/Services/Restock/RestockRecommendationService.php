<?php

namespace App\Services\Restock;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemInsightRanking;
use App\Models\User;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\InventoryHealth\InventoryHealthQueryService;
use App\Services\InventoryHealth\InventoryHealthSyncService;
use App\Services\ItemInsightQueryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RestockRecommendationService
{
    /** Minimum margin % (from item insights) to qualify as high margin. */
    public const HIGH_MARGIN_MIN_PCT = 25.0;

    /** High-margin SKUs with more days of cover than this are treated as adequately stocked. */
    public const HIGH_MARGIN_MAX_COVER_DAYS = 60.0;

    public const PER_PAGE = 100;

    public const MAX_PAGES = 5;

    public const MAX_ITEMS = self::PER_PAGE * self::MAX_PAGES;

    /** Display sales totals from inventory-health window (default). */
    public const SALES_WINDOW_HEALTH = 0;

    /** Display sales totals from rolling 12 calendar months (warehouse_item_monthly_stats). */
    public const SALES_WINDOW_YEAR = 365;

    public function __construct(
        private readonly InventoryHealthQueryService $inventoryHealth,
        private readonly ItemInsightQueryService $itemInsights,
        private readonly RestockSkuConfidenceService $skuConfidence,
        private readonly RestockPipelineTotalsByItem $pipelineTotals,
        private readonly RestockRollingYearStatsByItem $rollingYearStats,
    ) {}

    /**
     * @return array{
     *     tab: string,
     *     item_type: ?ItemType,
     *     health_windows: array{period_from: string, period_to: string, extended_from: string, period_days: int},
     *     health_source: string,
     *     insight_period: ?\App\Models\ItemInsightMonth,
     *     insight_calculated: bool,
     *     sales_window: int,
     *     fast_moving: LengthAwarePaginator<int, array<string, mixed>>,
     *     high_margin: LengthAwarePaginator<int, array<string, mixed>>,
     * }
     */
    public function build(Request $request, ?User $user, ?string $tab = null): array
    {
        $tab = $this->normalizeTab($tab ?? $request->query('tab'));
        $salesWindow = $this->normalizeSalesWindow($request);
        $itemType = $this->normalizeItemTypeFilter($request->query('item_type'));
        $healthRequest = $this->healthRequest($request);
        $windows = $this->inventoryHealth->resolveWindows($healthRequest);
        $meta = $this->inventoryHealth->pageMeta($healthRequest);

        $healthByItem = $this->filterHealthByItemType(
            $this->inventoryHealth
                ->companyHealthRows($healthRequest, $user)
                ->keyBy('id'),
            $itemType,
        );

        $insightPeriod = $this->itemInsights->latestCalculatedMonthlyPeriod();
        $insightCalculated = $insightPeriod !== null;
        $insightIndex = $insightCalculated
            ? $this->insightRankIndex($insightPeriod->year, $insightPeriod->month)
            : [];

        $fastMoving = $this->paginateRecommendationRows(
            $request,
            $this->enrichRecommendationRows(
                $this->heroProductRecommendations($healthByItem, $insightIndex, $windows)
                    ->take(self::MAX_ITEMS)
                    ->values(),
                $windows,
                $salesWindow,
            ),
        );
        $highMargin = $this->paginateRecommendationRows(
            $request,
            $this->enrichRecommendationRows(
                $this->highMarginRecommendations($healthByItem, $insightIndex, $insightPeriod?->year, $insightPeriod?->month)
                    ->take(self::MAX_ITEMS)
                    ->values(),
                $windows,
                $salesWindow,
            ),
        );

        return [
            'tab' => $tab,
            'sales_window' => $salesWindow,
            'item_type' => $itemType,
            'health_windows' => $windows,
            'health_source' => $meta['source'],
            'insight_period' => $insightPeriod,
            'insight_calculated' => $insightCalculated,
            'fast_moving' => $fastMoving,
            'high_margin' => $highMargin,
        ];
    }

    private function normalizeTab(?string $tab): string
    {
        return in_array($tab, ['fast', 'margin'], true) ? $tab : 'fast';
    }

    /**
     * @return array<string, string> query value => label (empty key = all restock-eligible types)
     */
    public static function itemTypeFilterOptions(): array
    {
        return [
            '' => 'All types',
            (string) ItemType::ITEM->value => ItemType::ITEM->label(),
            (string) ItemType::ASSET_LANCAR->value => ItemType::ASSET_LANCAR->label(),
        ];
    }

    /**
     * @return array<string, string> query value => label
     */
    public static function salesWindowFilterOptions(): array
    {
        return [
            '' => 'Health window',
            (string) self::SALES_WINDOW_YEAR => '12 months (≈365 days)',
        ];
    }

    public function normalizeSalesWindow(Request $request): int
    {
        $raw = $request->query('sales_window');
        if ($raw === null || $raw === '' || $raw === '30' || $raw === 'health') {
            return self::SALES_WINDOW_HEALTH;
        }

        if (in_array((string) $raw, ['365', '12m', '12', 'year'], true)) {
            return self::SALES_WINDOW_YEAR;
        }

        return self::SALES_WINDOW_HEALTH;
    }

    public function normalizeItemTypeFilter(mixed $raw): ?ItemType
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = is_string($raw) ? strtolower(trim($raw)) : $raw;

        return match ($value) {
            'item', 'manufactured', (string) ItemType::ITEM->value, ItemType::ITEM->value => ItemType::ITEM,
            'asset_lancar', 'assetlancar', 'asset-lancar', (string) ItemType::ASSET_LANCAR->value, ItemType::ASSET_LANCAR->value => ItemType::ASSET_LANCAR,
            default => null,
        };
    }

    /**
     * @param  Collection<int, Item>  $healthByItem
     * @return Collection<int, Item>
     */
    private function filterHealthByItemType(Collection $healthByItem, ?ItemType $itemType): Collection
    {
        if ($itemType === null) {
            return $healthByItem;
        }

        return $healthByItem
            ->filter(fn (Item $item) => ItemType::coerce($item->type) === $itemType)
            ->values();
    }

    /**
     * Reuse default inventory-health windows (last 30 days) unless the user set an explicit range.
     */
    private function healthRequest(Request $request): Request
    {
        $health = Request::create(
            $request->url(),
            $request->method(),
            array_filter([
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ], fn ($value) => $value !== null && $value !== ''),
        );
        $health->setUserResolver(fn () => $request->user());

        return $health;
    }

    /**
     * @param  Collection<int, Item>  $healthByItem
     * @param  array<int, array<string, mixed>>  $insightIndex
     * @return Collection<int, array<string, mixed>>
     */
    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     */
    private function heroProductRecommendations(Collection $healthByItem, array $insightIndex, array $windows): Collection
    {
        $periodDays = max(1, (int) $windows['period_days']);

        $rows = $healthByItem
            ->filter(function (Item $item) use ($periodDays) {
                return $this->qualifiesForHeroProductTab($item, $periodDays);
            })
            ->map(function (Item $item) use ($insightIndex, $periodDays) {
                $cover = $item->health['days_of_cover'] ?? null;
                $stock = (float) $item->current_stock;
                $monthlyNet = RestockNetSell::monthlyRateFromPeriod((float) $item->net_period, $periodDays);
                $insight = $insightIndex[$item->id] ?? null;

                $reasons = [$item->health['rec'] ?? 'Restock'];
                $velocityTier = RestockNetSell::velocityTier($monthlyNet);
                if ($velocityTier !== RestockNetSell::TIER_BELOW) {
                    $reasons[] = RestockNetSell::velocityTierLabels()[$velocityTier]
                        .': ≈'.number_format($monthlyNet, 1).' net units/mo (health window)';
                }
                if ($stock <= 0) {
                    $reasons[] = 'Sold out with recent net sales';
                }
                if ($insight !== null) {
                    if (($insight['fastest_rank'] ?? null) !== null) {
                        $reasons[] = 'Item Insights velocity rank #'.$insight['fastest_rank'].' this month';
                    }
                    if (($insight['restock_alert_rank'] ?? null) !== null && $insight['alert_detail']) {
                        $reasons[] = (string) $insight['alert_detail'];
                    }
                }

                return $this->rowFromHealthItem($item, [
                    'reasons' => array_values(array_unique($reasons)),
                    'insight' => $insight,
                    'monthly_net' => $monthlyNet,
                    'sort_cover' => $cover ?? 0.0,
                    'sort_net' => (float) $item->net_period,
                ]);
            })
            ->sort(function (array $a, array $b) {
                return [
                    $a['sort_cover'],
                    -$a['sort_net'],
                    $a['item_id'],
                ] <=> [
                    $b['sort_cover'],
                    -$b['sort_net'],
                    $b['item_id'],
                ];
            })
            ->values();

        return $rows;
    }

    private function qualifiesForHeroProductTab(Item $item, int $periodDays): bool
    {
        $key = $item->health['key'] ?? InventoryHealthClassifier::INACTIVE;
        $monthlyNet = RestockNetSell::monthlyRateFromPeriod((float) $item->net_period, $periodDays);
        $hasMediumPlusVelocity = RestockNetSell::velocityTier($monthlyNet) !== RestockNetSell::TIER_BELOW;

        if ($key === InventoryHealthClassifier::LOW) {
            return true;
        }

        if (! $hasMediumPlusVelocity) {
            return false;
        }

        if (in_array($key, [InventoryHealthClassifier::DEAD, InventoryHealthClassifier::OVERSTOCK, InventoryHealthClassifier::INACTIVE], true)) {
            return false;
        }

        $cover = $item->health['days_of_cover'] ?? null;

        return $cover === null
            || $cover < InventoryHealthClassifier::OVERSTOCK_COVER_DAYS;
    }

    /**
     * @param  Collection<int, Item>  $healthByItem
     * @param  array<int, array<string, mixed>>  $insightIndex
     * @return Collection<int, array<string, mixed>>
     */
    private function highMarginRecommendations(
        Collection $healthByItem,
        array $insightIndex,
        ?int $year,
        ?int $month,
    ): Collection {
        if ($year === null || $month === null) {
            return collect();
        }

        $profitable = ItemInsightRanking::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('category', ItemInsightRanking::CATEGORY_MOST_PROFITABLE)
            ->orderBy('rank')
            ->get();

        $rows = collect();
        foreach ($profitable as $ranking) {
            $margin = $ranking->margin_pct;
            if ($margin === null || (float) $margin < self::HIGH_MARGIN_MIN_PCT) {
                continue;
            }

            $item = $healthByItem->get($ranking->item_id);
            if (! $item) {
                continue;
            }

            $healthKey = $item->health['key'] ?? InventoryHealthClassifier::INACTIVE;
            if (in_array($healthKey, [InventoryHealthClassifier::DEAD, InventoryHealthClassifier::OVERSTOCK], true)) {
                continue;
            }

            $cover = $item->health['days_of_cover'] ?? null;
            if ($cover !== null && $cover > self::HIGH_MARGIN_MAX_COVER_DAYS && $healthKey === InventoryHealthClassifier::HEALTHY) {
                continue;
            }

            $insight = $insightIndex[$ranking->item_id] ?? null;
            $reasons = [
                sprintf('Margin %s%% · profit rank #%d', number_format((float) $margin, 1), $ranking->rank),
            ];
            if ($healthKey === InventoryHealthClassifier::LOW) {
                $reasons[] = $item->health['rec'] ?? 'Low stock vs sell rate';
            } elseif ($cover !== null) {
                $reasons[] = sprintf('≈%s days of cover at current sell rate', number_format($cover, 1));
            }

            $rows->push($this->rowFromHealthItem($item, [
                'reasons' => $reasons,
                'insight' => $insight,
                'margin_pct' => (float) $margin,
                'profit' => (float) $ranking->profit,
                'profit_rank' => $ranking->rank,
                'sort_margin' => (float) $margin,
                'sort_profit' => (float) $ranking->profit,
            ]));
        }

        return $rows
            ->sort(function (array $a, array $b) {
                return [
                    -$a['sort_margin'],
                    -$a['sort_profit'],
                    $a['item_id'],
                ] <=> [
                    -$b['sort_margin'],
                    -$b['sort_profit'],
                    $b['item_id'],
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    /**
     * @param  Collection<int, array<string, mixed>>  $rows  already capped to MAX_ITEMS
     */
    private function enrichRecommendationRows(Collection $rows, array $windows, int $salesWindow): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $withConfidence = $this->withSkuConfidence($rows, $windows);

        return $this->applySalesWindowDisplay($withConfidence, $windows, $salesWindow);
    }

    private function paginateRecommendationRows(Request $request, Collection $rows): LengthAwarePaginator
    {
        $capped = $rows->values();
        $perPage = self::PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));

        $pageSlice = $capped->forPage($page, $perPage)->values();
        $itemIds = $pageSlice->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $pipeline = $this->pipelineTotals->forItems($itemIds);

        $pageRows = $pageSlice->map(function (array $row) use ($pipeline) {
            $itemId = (int) $row['item_id'];
            $pipe = $pipeline[$itemId] ?? [
                'qty_restock' => 0,
                'qty_production' => 0,
                'qty_shipped' => 0,
            ];

            return array_merge($row, $pipe);
        })->values();

        return new LengthAwarePaginator(
            $pageRows,
            $capped->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function rowFromHealthItem(Item $item, array $extra): array
    {
        return array_merge([
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_code' => $item->code,
            'item_type' => $item->type,
            'stock_qty' => (float) $item->current_stock,
            'net_period' => (float) $item->net_period,
            'days_of_cover' => $item->health['days_of_cover'] ?? null,
            'health_key' => $item->health['key'] ?? null,
            'health_label' => $item->health['label'] ?? null,
            'show_url' => $item->showUrl(),
        ], $extra);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function insightRankIndex(int $year, int $month): array
    {
        $categories = [
            ItemInsightRanking::CATEGORY_FASTEST_SELLING => 'fastest_rank',
            ItemInsightRanking::CATEGORY_BEST_SELLING => 'best_selling_rank',
            ItemInsightRanking::CATEGORY_RESTOCK_ALERT => 'restock_alert_rank',
            ItemInsightRanking::CATEGORY_MOST_PROFITABLE => 'profit_rank',
        ];

        $index = [];
        foreach ($categories as $category => $field) {
            $rankings = ItemInsightRanking::query()
                ->where('year', $year)
                ->where('month', $month)
                ->where('category', $category)
                ->get(['item_id', 'rank', 'margin_pct', 'alert_detail', 'daily_velocity']);

            foreach ($rankings as $row) {
                $itemId = (int) $row->item_id;
                $index[$itemId] ??= ['item_id' => $itemId];
                $index[$itemId][$field] = (int) $row->rank;
                if ($category === ItemInsightRanking::CATEGORY_RESTOCK_ALERT) {
                    $index[$itemId]['alert_detail'] = $row->alert_detail;
                }
                if ($category === ItemInsightRanking::CATEGORY_FASTEST_SELLING) {
                    $index[$itemId]['daily_velocity'] = $row->daily_velocity;
                }
                if ($category === ItemInsightRanking::CATEGORY_MOST_PROFITABLE) {
                    $index[$itemId]['margin_pct'] = $row->margin_pct;
                }
            }
        }

        return $index;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     * @return Collection<int, array<string, mixed>>
     */
    private function withSkuConfidence(Collection $rows, array $windows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $context = [];
        foreach ($rows as $row) {
            $context[(int) $row['item_id']] = [
                'net_period' => (float) $row['net_period'],
                'period_days' => $windows['period_days'],
                'stock_qty' => (float) $row['stock_qty'],
                'days_of_cover' => $row['days_of_cover'] !== null ? (float) $row['days_of_cover'] : null,
                'health_key' => $row['health_key'] ?? null,
            ];
        }

        $worth = $this->skuConfidence->forItems(array_keys($context), $context);

        return $rows->map(function (array $row) use ($worth) {
            $itemId = (int) $row['item_id'];
            $row['worth'] = $worth[$itemId] ?? [
                'pattern' => RestockSkuConfidenceService::PATTERN_MODERATE,
                'pattern_label' => RestockSkuConfidenceService::patternLabels()[RestockSkuConfidenceService::PATTERN_MODERATE],
                'confidence' => RestockSkuConfidenceService::CONFIDENCE_MEDIUM,
                'confidence_label' => RestockSkuConfidenceService::confidenceLabels()[RestockSkuConfidenceService::CONFIDENCE_MEDIUM],
                'detail' => '',
            ];

            return $row;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     * @return Collection<int, array<string, mixed>>
     */
    private function applySalesWindowDisplay(Collection $rows, array $windows, int $salesWindow): Collection
    {
        $periodDays = max(1, (int) $windows['period_days']);
        $asOf = Carbon::parse($windows['period_to']);

        if ($salesWindow !== self::SALES_WINDOW_YEAR) {
            return $rows->map(function (array $row) use ($periodDays) {
                $row['display_net_sold'] = (float) $row['net_period'];
                $row['display_monthly_net'] = (float) ($row['monthly_net'] ?? RestockNetSell::monthlyRateFromPeriod((float) $row['net_period'], $periodDays));
                $row['sales_window_days'] = $periodDays;

                return $row;
            });
        }

        $itemIds = $rows->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $yearStats = $this->rollingYearStats->summariesForItems($itemIds, $asOf);

        return $rows->map(function (array $row) use ($yearStats) {
            $itemId = (int) $row['item_id'];
            $stats = $yearStats[$itemId] ?? ['net_12m' => 0.0, 'monthly_avg' => 0.0];
            $row['display_net_sold'] = (float) $stats['net_12m'];
            $row['display_monthly_net'] = (float) $stats['monthly_avg'];
            $row['sales_window_days'] = self::SALES_WINDOW_YEAR;

            if (array_key_exists('monthly_net', $row)) {
                $tier = RestockNetSell::velocityTier($stats['monthly_avg']);
                $row['monthly_net'] = $stats['monthly_avg'];
                $reasons = array_values(array_filter(
                    $row['reasons'] ?? [],
                    fn (string $reason) => ! str_contains($reason, 'net units/mo'),
                ));
                if ($tier !== RestockNetSell::TIER_BELOW) {
                    $reasons[] = RestockNetSell::velocityTierLabels()[$tier]
                        .': ≈'.number_format($stats['monthly_avg'], 1).' net units/mo (12 mo avg)';
                }
                $row['reasons'] = $reasons;
            }

            return $row;
        });
    }

    /**
     * @return array{period_from: string, period_to: string}
     */
    public function defaultHealthWindowLabels(): array
    {
        $windows = app(InventoryHealthSyncService::class)->windows();

        return [
            'period_from' => $windows['period_from'],
            'period_to' => $windows['period_to'],
        ];
    }
}
