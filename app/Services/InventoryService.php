<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\WarehouseItem;
use Exception;

class InventoryService
{
    /**
     * @throws Exception
     */
    public function add(int $warehouseId, Item $item, float $quantity): WarehouseItem
    {
        return $this->adjustStock($warehouseId, $item, $quantity);
    }

    /**
     * @throws Exception
     */
    public function deduct(int $warehouseId, Item $item, float $quantity): WarehouseItem
    {
        return $this->adjustStock($warehouseId, $item, -$quantity);
    }

    /**
     * Unified method. Physical warehouses cannot go negative; virtual warehouses can.
     * The {@see WarehouseItem} save gate is the source of truth.
     */
    public function adjustStock(int $warehouseId, Item $item, float $quantity): WarehouseItem
    {
        if ($item->type === ItemType::SERVICE) {
            throw new Exception('Inventory tracking not applicable for Service items.');
        }

        $addrbook = Addrbook::find($warehouseId);
        if (! $addrbook) {
            throw new Exception('Addrbook (Warehouse) not found.');
        }

        return WarehouseItem::applyDelta($warehouseId, $item->id, $quantity, (int) $addrbook->type);
    }
}
