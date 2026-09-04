<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\WarehouseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemAvailabilityService
{
    /**
     * Sellable availability: non-deleted physical warehouses only.
     * Virtual warehouses (which allow negative stock) are excluded.
     */
    public function availableQuantity(int $itemId): float
    {
        return (float) WarehouseItem::query()
            ->where('item_id', $itemId)
            ->forAvailableStock()
            ->sum('quantity');
    }

    /**
     * @param  Collection<int, WarehouseItem>  $warehouseItems
     * @return array{
     *     physical: Collection<int, WarehouseItem>,
     *     virtual: Collection<int, WarehouseItem>,
     *     deleted: Collection<int, WarehouseItem>,
     *     available: float,
     *     virtual_stock: float,
     *     deleted_stock: float
     * }
     */
    public function partitionWarehouseItems(Collection $warehouseItems): array
    {
        $physical = $warehouseItems
            ->filter(fn (WarehouseItem $row) => $this->isPhysicalActive($row))
            ->values();
        $virtual = $warehouseItems
            ->filter(fn (WarehouseItem $row) => $this->isVirtualActive($row))
            ->values();
        $deleted = $warehouseItems
            ->filter(fn (WarehouseItem $row) => $this->isDeletedWarehouse($row))
            ->values();

        return [
            'physical' => $physical,
            'virtual' => $virtual,
            'deleted' => $deleted,
            'available' => (float) $physical->sum('quantity'),
            'virtual_stock' => (float) $virtual->sum('quantity'),
            'deleted_stock' => (float) $deleted->sum('quantity'),
        ];
    }

    /**
     * Sync items.qty from existing warehouse_item rows (physical, non-deleted).
     * Does not modify per-warehouse quantities — those stay owned by transaction posting.
     *
     * @return array{available: float, previous_qty: float}
     */
    public function recalculate(Item $item): array
    {
        return DB::transaction(function () use ($item) {
            $previousQty = (float) $item->qty;

            $available = $this->availableQuantity($item->id);
            $item->qty = $available;
            $item->save();

            return [
                'available' => $available,
                'previous_qty' => $previousQty,
            ];
        });
    }

    protected function isPhysicalActive(WarehouseItem $row): bool
    {
        return $row->warehouse
            && ! $row->warehouse->trashed()
            && (int) $row->warehouse->type === Addrbook::TYPE_WAREHOUSE;
    }

    protected function isVirtualActive(WarehouseItem $row): bool
    {
        return $row->warehouse
            && ! $row->warehouse->trashed()
            && (int) $row->warehouse->type === Addrbook::TYPE_V_WAREHOUSE;
    }

    protected function isDeletedWarehouse(WarehouseItem $row): bool
    {
        return $row->warehouse
            && $row->warehouse->trashed()
            && Addrbook::typeIsWarehouse((int) $row->warehouse->type);
    }
}
