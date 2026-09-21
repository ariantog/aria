<?php

namespace App\Services\Restock;

use App\Models\RestockCell;

class RestockPipelineTotalsByItem
{
    /**
     * Sum restock-sheet pipeline qty across all sheets per SKU.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array{
     *     qty_restock: int,
     *     qty_production: int,
     *     qty_shipped: int,
     *     sheet_links: list<array{id: int, name: string, url: string}>
     * }>
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

        $cells = RestockCell::query()
            ->whereIn('item_id', $itemIds)
            ->with(['sheet:id,name'])
            ->get(['item_id', 'restock_sheet_id', 'qty_restock']);

        $linksByItem = [];
        foreach ($cells as $cell) {
            $sheet = $cell->sheet;
            if ($sheet === null) {
                continue;
            }

            $itemId = (int) $cell->item_id;
            $sheetId = (int) $sheet->id;
            $existing = $linksByItem[$itemId][$sheetId] ?? null;
            $qty = (int) $cell->qty_restock;
            if ($existing === null || $qty > ($existing['sort_qty'] ?? 0)) {
                $linksByItem[$itemId][$sheetId] = [
                    'id' => $sheetId,
                    'name' => (string) $sheet->name,
                    'url' => route('restock.sheets.show', ['sheet' => $sheetId]),
                    'sort_qty' => $qty,
                ];
            }
        }

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $links = array_values($linksByItem[$itemId] ?? []);
            usort($links, fn (array $a, array $b) => [
                -($a['sort_qty'] ?? 0),
                $a['name'],
                $a['id'],
            ] <=> [
                -($b['sort_qty'] ?? 0),
                $b['name'],
                $b['id'],
            ]);
            foreach ($links as &$link) {
                unset($link['sort_qty']);
            }
            unset($link);

            $map[$itemId] = [
                'qty_restock' => (int) $row->qty_restock,
                'qty_production' => (int) $row->qty_production,
                'qty_shipped' => (int) $row->qty_shipped,
                'sheet_links' => $links,
            ];
        }

        foreach ($itemIds as $itemId) {
            if (isset($map[$itemId])) {
                continue;
            }
            $links = array_values($linksByItem[$itemId] ?? []);
            foreach ($links as &$link) {
                unset($link['sort_qty']);
            }
            unset($link);
            $map[$itemId] = [
                'qty_restock' => 0,
                'qty_production' => 0,
                'qty_shipped' => 0,
                'sheet_links' => $links,
            ];
        }

        return $map;
    }
}
