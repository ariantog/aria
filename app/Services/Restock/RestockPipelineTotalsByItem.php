<?php

namespace App\Services\Restock;

use App\Models\RestockCell;

class RestockPipelineTotalsByItem
{
    /**
     * Sum restock-sheet pipeline qty across all sheets per SKU.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array{qty_restock: int, qty_production: int, qty_shipped: int}>
     */
    public function forItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $rows = RestockCell::query()
            ->whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->select('item_id')
            ->selectRaw('COALESCE(SUM(qty_restock), 0) as qty_restock')
            ->selectRaw('COALESCE(SUM(qty_production), 0) as qty_production')
            ->selectRaw('COALESCE(SUM(qty_shipped), 0) as qty_shipped')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->item_id] = [
                'qty_restock' => (int) $row->qty_restock,
                'qty_production' => (int) $row->qty_production,
                'qty_shipped' => (int) $row->qty_shipped,
            ];
        }

        return $map;
    }
}
