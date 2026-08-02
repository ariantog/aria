<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Addrbook; // Updated from Warehouse
use App\Models\Item;
use App\Models\WarehouseItem;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * @throws Exception
     */
    public function add(int $warehouseId, Item $item, float $quantity): WarehouseItem
    {
        // ItemsManagerHelper::add logic:
        // if($item->type == Item::TYPE_SERVICE) return true;

        $addrbook = Addrbook::find($warehouseId);
        if (! $addrbook) {
            throw new Exception('Addrbook (Warehouse) not found.');
        }

        // Ensure it's a warehouse?
        if ($addrbook->type != Addrbook::TYPE_WAREHOUSE && $addrbook->type != Addrbook::TYPE_V_WAREHOUSE) {
            // Or strict check? Legacy checked checks 'customers.type' = Warehouse in findItemInWarehouse.
            // But add/deduct in helper didn't strictly check type of customer, it just used warehouse_id.
            // But we should probably check.
        }

        return $this->adjustStock($addrbook, $item, $quantity, 'add');
    }

    /**
     * @throws Exception
     */
    public function deduct(int $warehouseId, Item $item, float $quantity, bool $allowNegative = false): WarehouseItem
    {
        $addrbook = Addrbook::find($warehouseId);
        if (! $addrbook) {
            throw new Exception('Addrbook (Warehouse) not found.');
        }

        return $this->adjustStock($addrbook, $item, -$quantity, 'deduct', $allowNegative);
    }

    /**
     * Unified method
     */
    public function adjustStock(Addrbook $addrbook, Item $item, float $quantity, string $reason = 'adjustment', bool $allowNegative = false): WarehouseItem
    {
        // ItemsManagerHelper Logic: if($item->type == Item::TYPE_SERVICE) return true;
        // My definition of SERVICE might differ from legacy integer.
        // Legacy: Item::TYPE_SERVICE.
        // My Enum: ItemType::SERVICE.
        if ($item->type === ItemType::SERVICE) {
            // Legacy returns true (dummy success).
            // Returning a dummy WarehouseItem/null might be expected?
            // Helper returns "true" (bool) but method signature hints it usually returns wi.
            // If I return generic object or throw?
            // Let's create a dummy or throw.
            // Actually, the helper returns 'true' which might break callers expecting object.
            // But let's assume valid flow doesn't use the return value for Service items.
            // I'll throw strict exception that Service shouldn't be tracked, OR mock a return.
            // Legacy says "return true".
            throw new Exception('Inventory tracking not applicable for Service items.');
        }

        return DB::transaction(function () use ($addrbook, $item, $quantity, $allowNegative) {
            $wi = WarehouseItem::lockForUpdate()
                ->firstOrCreate(
                    ['warehouse_id' => $addrbook->id, 'item_id' => $item->id],
                    ['quantity' => 0]
                );

            // Legacy Logic:
            // if(!$can_minus && ($wi->quantity - $quantity) < 0)
            // throw new \Exception("{$item->code} cuma ada {$wi->quantity}, mau diambil {$quantity}");

            // Note: input $quantity here is signed for adjustStock (negative for deduct).
            // Helper 'deduct' takes positive Quantity and does -=.
            // My 'deduct' converts to negative.
            // So logic: ($wi->quantity + $quantity) < 0.

            if ($quantity < 0 && ! $allowNegative && ($wi->quantity + $quantity) < 0) {
                throw new Exception("{$item->code} cuma ada {$wi->quantity}, mau diambil ".abs($quantity));
            }

            $wi->quantity += $quantity;
            $wi->save();

            return $wi;
        });
    }
}
