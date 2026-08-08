<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Tag;
use App\Models\WarehouseItemMonthlyStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseArrangementService
{
    /**
     * @return Collection<int, Addrbook>
     */
    public function destinationWarehouses(): Collection
    {
        return Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{
     *     destination: Addrbook,
     *     demand_days: int,
     *     families: list<array<string, mixed>>,
     *     suggestions: list<array<string, mixed>>
     * }
     */
    public function buildSuggestions(int $destinationWarehouseId, int $demandDays = 365): array
    {
        $destination = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($destinationWarehouseId);

        $items = Item::query()
            ->where('type', ItemType::ITEM)
            ->whereNotNull('group_id')
            ->with(['group', 'tags'])
            ->get();

        if ($items->isEmpty()) {
            return [
                'destination' => $destination,
                'demand_days' => $demandDays,
                'families' => [],
                'suggestions' => [],
            ];
        }

        $itemIds = $items->pluck('id')->all();

        $stockMap = $this->loadStockMap($itemIds);
        $demandMap = $this->loadDemandMap($destinationWarehouseId, $itemIds, $demandDays);
        $warehouseNames = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->pluck('name', 'id');

        $families = [];
        foreach ($items as $item) {
            $master = $item->group?->master;
            if (! $master) {
                continue;
            }

            if (! isset($families[$master])) {
                $families[$master] = [
                    'name' => $item->group->name,
                    'items' => [],
                ];
            }

            $families[$master]['items'][] = $item;
        }

        $familyScores = [];
        foreach ($families as $master => $family) {
            $demandScore = 0.0;
            $presentCount = 0;
            $totalCount = count($family['items']);

            foreach ($family['items'] as $item) {
                $demandScore += $demandMap[$item->id] ?? 0.0;
                $destStock = $stockMap[$destinationWarehouseId][$item->id] ?? 0.0;
                if ($destStock > 0) {
                    $presentCount++;
                }
            }

            $familyScores[$master] = [
                'master' => $master,
                'name' => $family['name'],
                'demand_score' => $demandScore,
                'completeness_pct' => $totalCount > 0 ? round($presentCount / $totalCount * 100, 1) : 0.0,
                'present_count' => $presentCount,
                'total_count' => $totalCount,
            ];
        }

        $sortedFamilies = collect($familyScores)
            ->filter(fn (array $family) => $family['demand_score'] > 0)
            ->sortByDesc('demand_score')
            ->values()
            ->all();

        $suggestions = [];

        foreach ($sortedFamilies as $familyMeta) {
            $master = $familyMeta['master'];

            foreach ($families[$master]['items'] as $item) {
                $destStock = $stockMap[$destinationWarehouseId][$item->id] ?? 0.0;
                if ($destStock > 0) {
                    continue;
                }

                $bestSourceId = null;
                $bestSourceStock = 0.0;

                foreach ($stockMap as $warehouseId => $itemsStock) {
                    if ((int) $warehouseId === $destinationWarehouseId) {
                        continue;
                    }

                    $qty = $itemsStock[$item->id] ?? 0.0;
                    if ($qty > $bestSourceStock) {
                        $bestSourceStock = $qty;
                        $bestSourceId = (int) $warehouseId;
                    }
                }

                if (! $bestSourceId || $bestSourceStock <= 0) {
                    continue;
                }

                $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
                $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
                $itemDemand = $demandMap[$item->id] ?? 0.0;
                $suggestedQty = max(1, min((int) $bestSourceStock, (int) ceil($itemDemand) ?: 1));

                $suggestions[] = [
                    'master' => $master,
                    'master_name' => $familyMeta['name'],
                    'family_demand_score' => $familyMeta['demand_score'],
                    'completeness_pct' => $familyMeta['completeness_pct'],
                    'item_id' => $item->id,
                    'item_code' => $item->code,
                    'item_name' => $item->name,
                    'warna' => $warnaTag?->code ?? '-',
                    'size' => $sizeTag?->code ?? '-',
                    'item_demand' => $itemDemand,
                    'from_warehouse_id' => $bestSourceId,
                    'from_warehouse_name' => $warehouseNames[$bestSourceId] ?? 'Unknown',
                    'to_warehouse_id' => $destinationWarehouseId,
                    'to_warehouse_name' => $destination->name,
                    'source_stock' => (int) $bestSourceStock,
                    'suggested_qty' => $suggestedQty,
                ];
            }
        }

        return [
            'destination' => $destination,
            'demand_days' => $demandDays,
            'families' => $sortedFamilies,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, array<int, float>>
     */
    private function loadStockMap(array $itemIds): array
    {
        $stockMap = [];
        $rows = DB::table('warehouse_items')
            ->whereIn('item_id', $itemIds)
            ->where('quantity', '>', 0)
            ->get(['warehouse_id', 'item_id', 'quantity']);

        foreach ($rows as $row) {
            $stockMap[(int) $row->warehouse_id][(int) $row->item_id] = (float) $row->quantity;
        }

        return $stockMap;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    private function loadDemandMap(int $warehouseId, array $itemIds, int $demandDays): array
    {
        $startDate = now()->subDays($demandDays);
        $startKey = $startDate->year * 12 + $startDate->month;

        $rows = WarehouseItemMonthlyStat::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->whereRaw('(year * 12 + month) >= ?', [$startKey])
            ->selectRaw('item_id, SUM(sold_qty) as sold_qty, SUM(returned_qty) as returned_qty')
            ->groupBy('item_id')
            ->get();

        $demandMap = [];
        foreach ($rows as $row) {
            $demandMap[(int) $row->item_id] = max(0.0, (float) $row->sold_qty - (float) $row->returned_qty);
        }

        return $demandMap;
    }
}
