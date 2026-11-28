<?php

namespace App\Services\Marketplace;

use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Schema;

/**
 * listing_qty = max(0, physical − on_order − promo_lock − safety_buffer)
 */
class MarketplaceListingSellableCalculator
{
    public function __construct(
        private OmnichannelSettingStore $settings,
    ) {}

    public function listingQtyForWarehouseItem(int $itemId, int $warehouseId): int
    {
        if ($itemId <= 0 || $warehouseId <= 0 || ! Schema::hasTable('warehouse_item')) {
            return 0;
        }

        $row = WarehouseItem::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($row === null) {
            return 0;
        }

        $physical = (int) ($row->quantity ?? 0);
        $onOrder = (int) ($row->on_order ?? 0);
        $buffer = $this->safetyBuffer();
        $promoLock = 0;

        return max(0, $physical - $onOrder - $promoLock - $buffer);
    }

    public function safetyBuffer(): int
    {
        return max(0, $this->settings->getInt(
            OmnichannelSettingStore::KEY_SHOPEE_LOW_STOCK_THRESHOLD,
            0,
        ));
    }
}