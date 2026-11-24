<?php

namespace App\Services\TikTokShop;

use App\Models\MarketplaceWarehouseSync;
use App\Models\TiktokShopSync;

class WarehouseTikTokShopService
{
    public function syncForWarehouse(int $warehouseId): ?MarketplaceWarehouseSync
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_warehouse_syncs')) {
            return null;
        }

        return MarketplaceWarehouseSync::query()
            ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('id')
            ->first();
    }

    public function legacySyncForWarehouse(int $warehouseId): ?TiktokShopSync
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('tiktok_shop_syncs')) {
            return null;
        }

        return TiktokShopSync::query()
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('id')
            ->first();
    }

    public function hasWarehouseMapping(int $warehouseId): bool
    {
        return $this->syncForWarehouse($warehouseId) !== null
            || $this->legacySyncForWarehouse($warehouseId) !== null;
    }
}