<?php

namespace App\Services\Readonly;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemInsightRanking;
use App\Models\User;
use App\Services\InventoryHealth\InventoryHealthQueryService;
use App\Services\ItemInsightQueryService;
use App\Services\Restock\RestockNetSell;
use App\Services\Restock\RestockPipelineTotalsByItem;
use App\Services\Restock\RestockRecommendationService;
use App\Services\Restock\RestockRollingYearStatsByItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class ReadonlyRestockContextService
{
    public function __construct(
        private readonly InventoryHealthQueryService $inventoryHealth,
        private readonly ItemInsightQueryService $itemInsights,
        private readonly RestockRecommendationService $recommendations,
        private readonly RestockPipelineTotalsByItem $pipelineTotals,
        private readonly RestockRollingYearStatsByItem $rollingYearStats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, ?User $user): array
    {
        $itemType = $this->resolveItemType($request);
        $salesWindow = $this->recommendations->normalizeSalesWindow($request);
        $bestSellingOnly = $this->boolQuery($request, 'best_selling_only', false);
        $topN = $this->positiveIntQuery($request, 'top_n');

        $healthRequest = $this->healthRequest($request);
        $windows = $this->inventoryHealth->resolveWindows($healthRequest);
        $periodDays = max(1, (int) $windows['period_days']);

        $healthByItem = $this->inventoryHealth
            ->companyHealthRows($healthRequest, $user)
            ->filter(fn (Item $item) => ItemType::coerce($item->type) === $itemType)
            ->keyBy('id');

        $insightPeriod = $this->itemInsights->latestCalculatedMonthlyPeriod();
        $bestSellingRanks = $this->bestSellingRankMap($insightPeriod?->year, $insightPeriod?->month, $topN);

        if ($bestSellingOnly || $topN !== null) {
            $healthByItem = $healthByItem->filter(
                fn (Item $item, int $id) => isset($bestSellingRanks[$id]),
            );
        }

        $itemIds = $healthByItem->keys()->map(fn ($id) => (int) $id)->all();
        $pipeline = $this->pipelineTotals->forItems($itemIds);
        $ariaSuggested = $this->recommendations->recommendedRestockQuantitiesForItems($request, $user, $itemIds);

        $asOf = Carbon::parse($windows['period_to']);
        $yearStats = $salesWindow === RestockRecommendationService::SALES_WINDOW_YEAR
            ? $this->rollingYearStats->summariesForItems($itemIds, $asOf)
            : [];

        $rows = [];
        foreach ($healthByItem as $item) {
            $itemId = (int) $item->id;
            $pipe = $pipeline[$itemId] ?? [
                'qty_restock' => 0,
                'qty_production' => 0,
                'qty_shipped' => 0,
                'sheet_links' => [],
            ];

            if ($salesWindow === RestockRecommendationService::SALES_WINDOW_YEAR) {
                $stats = $yearStats[$itemId] ?? ['net_12m' => 0.0, 'monthly_avg' => 0.0];
                $monthlyNet = (float) $stats['monthly_avg'];
                $netPeriod = (float) $stats['net_12m'];
                $salesWindowDays = RestockRecommendationService::SALES_WINDOW_YEAR;
            } else {
                $netPeriod = (float) $item->net_period;
                $monthlyNet = RestockNetSell::monthlyRateFromPeriod($netPeriod, $periodDays);
                $salesWindowDays = $periodDays;
            }

            $rows[] = [
                'sku' => $item->code,
                'item_id' => $itemId,
                'name' => $item->name,
                'type' => ItemType::coerce($item->type)?->value,
                'type_label' => ItemType::coerce($item->type)?->label(),
                'best_selling_rank' => $bestSellingRanks[$itemId] ?? null,
                'stock_qty' => (float) $item->current_stock,
                'days_of_cover' => $item->health['days_of_cover'] ?? null,
                'health_key' => $item->health['key'] ?? null,
                'health_label' => $item->health['label'] ?? null,
                'net_period' => $netPeriod,
                'monthly_net' => $monthlyNet,
                'sales_window_days' => $salesWindowDays,
                'qty_restock' => (int) ($pipe['qty_restock'] ?? 0),
                'qty_production' => (int) ($pipe['qty_production'] ?? 0),
                'qty_shipped' => (int) ($pipe['qty_shipped'] ?? 0),
                'sheet_links' => $pipe['sheet_links'] ?? [],
                'aria_suggested_qty' => (int) ($ariaSuggested[$itemId] ?? 0),
                'aria_suggested_qty_note' => 'Reference only — Grok may override.',
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $rankA = $a['best_selling_rank'] ?? PHP_INT_MAX;
            $rankB = $b['best_selling_rank'] ?? PHP_INT_MAX;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return $a['item_id'] <=> $b['item_id'];
        });

        return [
            'data' => $rows,
            'meta' => [
                'item_type' => $itemType->value,
                'item_type_label' => $itemType->label(),
                'best_selling_only' => $bestSellingOnly,
                'top_n' => $topN,
                'sales_window' => $salesWindow,
                'health_windows' => $windows,
                'insight_period' => $insightPeriod === null ? null : [
                    'year' => (int) $insightPeriod->year,
                    'month' => (int) $insightPeriod->month,
                ],
                'row_count' => count($rows),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function insightPeriod(): array
    {
        $period = $this->itemInsights->latestCalculatedMonthlyPeriod();

        return [
            'data' => $period === null ? null : [
                'year' => (int) $period->year,
                'month' => (int) $period->month,
                'calculated_at' => $period->updated_at?->toIso8601String(),
            ],
            'meta' => [
                'calculated' => $period !== null,
            ],
        ];
    }

    private function resolveItemType(Request $request): ItemType
    {
        $raw = $request->query('item_type', 'assetlancar');
        $normalized = $this->recommendations->normalizeItemTypeFilter($raw);

        return $normalized ?? ItemType::ASSET_LANCAR;
    }

    private function healthRequest(Request $request): Request
    {
        $warehouseId = $this->recommendations->resolveHealthWarehouseId($request);

        $health = Request::create(
            $request->url(),
            $request->method(),
            array_filter([
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'warehouse_id' => $warehouseId,
            ], fn ($value) => $value !== null && $value !== ''),
        );
        $health->setUserResolver(fn () => $request->user());

        return $health;
    }

    /**
     * @return array<int, int> item_id => rank
     */
    private function bestSellingRankMap(?int $year, ?int $month, ?int $topN): array
    {
        if ($year === null || $month === null) {
            return [];
        }

        $query = ItemInsightRanking::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('category', ItemInsightRanking::CATEGORY_BEST_SELLING)
            ->orderBy('rank');

        if ($topN !== null) {
            $query->limit($topN);
        }

        $map = [];
        foreach ($query->get(['item_id', 'rank']) as $row) {
            $map[(int) $row->item_id] = (int) $row->rank;
        }

        return $map;
    }

    private function boolQuery(Request $request, string $key, bool $default): bool
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private function positiveIntQuery(Request $request, string $key): ?int
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }
}