<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Services\JubelioService;
use App\Services\JubelioStockCheckService;
use App\Services\WarehouseJubelioStockService;
use Illuminate\Support\Collection;

class ItemJubelioStockService
{
    public function __construct(
        private JubelioService $jubelioService,
        private JubelioStockCheckService $stockCheckService,
        private WarehouseJubelioStockService $warehouseJubelioStockService,
    ) {}

    /**
     * @param  Collection<int, \App\Models\WarehouseItem>  $warehouseItems
     * @return array{
     *     total: array{
     *         linked: bool,
     *         on_hand: ?float,
     *         on_order: ?float,
     *         reserved: ?float,
     *         available: ?float,
     *         fetch_failed: bool,
     *     },
     *     by_warehouse: array<int, array{
     *         linked: bool,
     *         location_name: string,
     *         on_hand: ?float,
     *         on_order: ?float,
     *         reserved: ?float,
     *         available: ?float,
     *         mismatch: bool,
     *     }>,
     * }
     */
    public function detailStockForItem(Item $item, Collection $warehouseItems): array
    {
        $emptyTotal = [
            'linked' => false,
            'on_hand' => null,
            'on_order' => null,
            'reserved' => null,
            'available' => null,
            'fetch_failed' => false,
        ];

        $jubelioId = (int) ($item->jubelio_item_id ?? 0);
        if ($jubelioId <= 0) {
            return ['total' => $emptyTotal, 'by_warehouse' => []];
        }

        $stockRow = $this->fetchStockRow($jubelioId);
        if ($stockRow === null) {
            return [
                'total' => [
                    'linked' => true,
                    'on_hand' => null,
                    'on_order' => null,
                    'reserved' => null,
                    'available' => null,
                    'fetch_failed' => true,
                ],
                'by_warehouse' => [],
            ];
        }

        $total = $stockRow['total_stocks'] ?? [];
        $byWarehouse = [];

        foreach ($warehouseItems as $warehouseItem) {
            $warehouseId = (int) $warehouseItem->warehouse_id;
            $sync = $this->warehouseJubelioStockService->syncForWarehouse($warehouseId);
            if ($sync === null) {
                continue;
            }

            $locationStock = $this->stockCheckService->locationStockFor(
                $stockRow,
                (int) $sync->jubelio_location_id,
            );

            $quantities = $locationStock !== null
                ? $this->stockCheckService->resolveLocationQuantities($locationStock)
                : null;

            $ariaQty = (float) $warehouseItem->quantity;
            $available = $quantities['available'] ?? null;

            $byWarehouse[$warehouseId] = [
                'linked' => true,
                'location_name' => (string) $sync->jubelio_location_name,
                'on_hand' => $quantities['on_hand'] ?? null,
                'on_order' => $quantities['on_order'] ?? null,
                'reserved' => $quantities['reserved'] ?? null,
                'available' => $available,
                'mismatch' => $available !== null && $ariaQty !== $available,
            ];
        }

        return [
            'total' => [
                'linked' => true,
                'on_hand' => (float) ($total['on_hand'] ?? 0),
                'on_order' => (float) ($total['on_order'] ?? 0),
                'reserved' => (float) ($total['reserved'] ?? 0),
                'available' => (float) ($total['available'] ?? 0),
                'fetch_failed' => false,
            ],
            'by_warehouse' => $byWarehouse,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchStockRow(int $jubelioItemId): ?array
    {
        $indexed = $this->jubelioService->fetchItemStocks([$jubelioItemId]);

        return $indexed[$jubelioItemId] ?? null;
    }
}
