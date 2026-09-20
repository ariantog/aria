<?php

namespace App\Services\Restock;

use App\Models\Item;
use App\Models\ItemInsightRanking;
use App\Models\User;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\InventoryHealth\InventoryHealthQueryService;
use App\Services\InventoryHealth\InventoryHealthSyncService;
use App\Services\ItemInsightQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RestockRecommendationService
{
    /** Minimum margin % (from item insights) to qualify as high margin. */
    public const HIGH_MARGIN_MIN_PCT = 25.0;

    /** High-margin SKUs with more days of cover than this are treated as adequately stocked. */
    public const HIGH_MARGIN_MAX_COVER_DAYS = 60.0;

    public function __construct(
        private readonly InventoryHealthQueryService $inventoryHealth,
        private readonly ItemInsightQueryService $itemInsights,
    ) {}

    /**
     * @return array{
     *     tab: string,
     *     health_windows: array{period_from: string, period_to: string, extended_from: string, period_days: int},
     *     health_source: string,
     *     insight_period: ?\App\Models\ItemInsightMonth,
     *     insight_calculated: bool,
     *     fast_moving: Collection<int, array<string, mixed>>,
     *     high_margin: Collection<int, array<string, mixed>>,
     * }
     */
    public function build(Request $request, ?User $user, ?string $tab = null): array
    {
        $tab = $this->normalizeTab($tab ?? $request->query('tab'));
        $healthRequest = $this->healthRequest($request);
        $windows = $this->inventoryHealth->resolveWindows($healthRequest);
        $meta = $this->inventoryHealth->pageMeta($healthRequest);

        $healthByItem = $this->inventoryHealth
            ->companyHealthRows($healthRequest, $user)
            ->keyBy('id');

        $insightPeriod = $this->itemInsights->latestCalculatedMonthlyPeriod();
        $insightCalculated = $insightPeriod !== null;
        $insightIndex = $insightCalculated
            ? $this->insightRankIndex($insightPeriod->year, $insightPeriod->month)
            : [];

        $fastMoving = $this->fastMovingRecommendations($healthByItem, $insightIndex);
        $highMargin = $this->highMarginRecommendations($healthByItem, $insightIndex, $insightPeriod?->year, $insightPeriod?->month);

        return [
            'tab' => $tab,
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
    private function fastMovingRecommendations(Collection $healthByItem, array $insightIndex): Collection
    {
        $rows = $healthByItem
            ->filter(function (Item $item) {
                $key = $item->health['key'] ?? null;

                return $key === InventoryHealthClassifier::LOW;
            })
            ->map(function (Item $item) use ($insightIndex) {
                $cover = $item->health['days_of_cover'] ?? null;
                $stock = (float) $item->current_stock;
                $insight = $insightIndex[$item->id] ?? null;

                $reasons = [$item->health['rec'] ?? 'Restock'];
                if ($stock <= 0) {
                    $reasons[] = 'Sold out with recent net sales';
                }
                if ($insight !== null) {
                    if (($insight['fastest_rank'] ?? null) !== null) {
                        $reasons[] = 'Fastest selling #'.$insight['fastest_rank'].' this month';
                    }
                    if (($insight['restock_alert_rank'] ?? null) !== null && $insight['alert_detail']) {
                        $reasons[] = (string) $insight['alert_detail'];
                    }
                }

                return $this->rowFromHealthItem($item, [
                    'reasons' => array_values(array_unique($reasons)),
                    'insight' => $insight,
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
            ->values()
            ->take(100);

        return $rows;
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
            ->values()
            ->take(50);
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
