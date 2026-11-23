<?php

namespace App\Services\Marketplace;

use App\Models\Shopeesync;
use Illuminate\Support\Facades\Schema;

/**
 * Map Shopee pickup location → Aria warehouse via marketplace_warehouse_syncs (legacy shopee_syncs fallback).
 */
class ShopeeSyncWarehouseResolver
{
    public function __construct(
        private MarketplaceWarehouseSyncResolver $warehouseSyncResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $pushData
     * @param  array<string, mixed>|null  $orderRow
     */
    public function ariaWarehouseIdForIngest(
        ?int $shopId,
        array $pushData = [],
        ?array $orderRow = null,
    ): int {
        if (! Schema::hasTable('shopee_syncs')) {
            return 0;
        }

        $pickup = app(ShopeeOrderPickupLocation::class)->extract($pushData, $orderRow);
        if ($pickup === null) {
            return $this->fallbackWarehouseForShop($shopId);
        }

        $hit = $this->warehouseSyncResolver->ariaWarehouseForShopeePickup($shopId, $pickup);
        if ($hit > 0) {
            return $hit;
        }

        return $this->fallbackWarehouseForShop($shopId);
    }

    /**
     * Legacy Indonesia pushes often omit product_location_id; one shop → one gudang row still resolves.
     */
    private function fallbackWarehouseForShop(?int $shopId): int
    {
        if ($shopId === null || $shopId <= 0) {
            return 0;
        }

        $warehouseIds = Shopeesync::query()
            ->where('shop_id', $shopId)
            ->where('warehouse_id', '>', 0)
            ->distinct()
            ->pluck('warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($warehouseIds->count() === 1) {
            return (int) $warehouseIds->first();
        }

        return 0;
    }
}