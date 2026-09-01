<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
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
     * Rebuild per-warehouse quantities from completed transactions, then
     * write items.qty to the available (physical, non-deleted) total.
     *
     * @return array{available: float, previous_qty: float}
     */
    public function recalculate(Item $item): array
    {
        return DB::transaction(function () use ($item) {
            $previousQty = (float) $item->qty;

            $this->rebuildWarehouseQuantities($item);

            $available = $this->availableQuantity($item->id);
            $item->qty = $available;
            $item->save();

            return [
                'available' => $available,
                'previous_qty' => $previousQty,
            ];
        });
    }

    protected function rebuildWarehouseQuantities(Item $item): void
    {
        $completed = Transaction::STATUS_COMPLETED;
        $depreciation = Transaction::TYPE_DEPRECIATION;

        $inbound = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('td.item_id', $item->id)
            ->where('t.status', $completed)
            ->where('t.type', '!=', $depreciation)
            ->whereNotNull('t.receiver_id')
            ->select('t.receiver_id as warehouse_id', DB::raw('SUM(td.quantity) as qty'))
            ->groupBy('t.receiver_id')
            ->get();

        $outbound = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('td.item_id', $item->id)
            ->where('t.status', $completed)
            ->where('t.type', '!=', $depreciation)
            ->whereNotNull('t.sender_id')
            ->select('t.sender_id as warehouse_id', DB::raw('SUM(-td.quantity) as qty'))
            ->groupBy('t.sender_id')
            ->get();

        $byWarehouse = [];
        foreach ($inbound as $row) {
            $id = (int) $row->warehouse_id;
            $byWarehouse[$id] = ($byWarehouse[$id] ?? 0) + (float) $row->qty;
        }
        foreach ($outbound as $row) {
            $id = (int) $row->warehouse_id;
            $byWarehouse[$id] = ($byWarehouse[$id] ?? 0) + (float) $row->qty;
        }

        $addrbooks = Addrbook::withTrashed()
            ->whereIn('id', array_keys($byWarehouse) ?: [0])
            ->whereIn('type', WarehouseItem::warehouseAddrbookTypes())
            ->get()
            ->keyBy('id');

        $touched = [];
        foreach ($byWarehouse as $warehouseId => $qty) {
            $addrbook = $addrbooks->get($warehouseId);
            if (! $addrbook) {
                continue;
            }

            $row = WarehouseItem::firstOrNew([
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
            ]);
            $row->warehouse_type = (string) $addrbook->type;
            $row->quantity = $qty;
            $row->save();
            $touched[] = $warehouseId;
        }

        $leftovers = WarehouseItem::query()
            ->where('item_id', $item->id)
            ->forWarehouseAddrbooks(withTrashed: true);

        if ($touched !== []) {
            $leftovers->whereNotIn('warehouse_id', $touched);
        }

        $leftovers->update(['quantity' => 0]);
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
