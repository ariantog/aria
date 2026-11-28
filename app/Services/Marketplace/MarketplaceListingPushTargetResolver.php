<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use Illuminate\Support\Facades\Schema;

/**
 * Mapped platform warehouses for listing qty push (pilot: per Aria gudang row).
 */
class MarketplaceListingPushTargetResolver
{
    /**
     * @return list<array{channel: string, shop_ref: string, shop_cipher: string, platform_location_id: string, platform_warehouse_id: string, warehouse_id: int, commerce_platform: string}>
     */
    public function targetsForWarehouse(int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return [];
        }

        $targets = [];

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            MarketplaceWarehouseSync::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id')
                ->get()
                ->each(function (MarketplaceWarehouseSync $row) use (&$targets): void {
                    $targets[] = [
                        'channel' => strtolower((string) $row->channel),
                        'shop_ref' => (string) $row->shop_ref,
                        'shop_cipher' => (string) ($row->shop_cipher ?? ''),
                        'platform_location_id' => (string) ($row->platform_location_id ?? ''),
                        'platform_warehouse_id' => (string) ($row->platform_warehouse_id ?? ''),
                        'warehouse_id' => (int) $row->warehouse_id,
                        'commerce_platform' => strtoupper(trim((string) ($row->commerce_platform ?? ''))),
                    ];
                });
        }

        if ($targets !== []) {
            return $targets;
        }

        if (Schema::hasTable('shopee_syncs')) {
            Shopeesync::query()
                ->where('warehouse_id', $warehouseId)
                ->orderByDesc('id')
                ->get()
                ->each(function (Shopeesync $row) use (&$targets): void {
                    $shopRef = (string) ((int) ($row->shop_id ?? 0) > 0 ? $row->shop_id : '');
                    $targets[] = [
                        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                        'shop_ref' => $shopRef,
                        'shop_cipher' => '',
                        'platform_location_id' => strtoupper(trim((string) ($row->shopee_location_id ?? ''))),
                        'platform_warehouse_id' => (string) ((int) ($row->shopee_warehouse_id ?? 0)),
                        'warehouse_id' => (int) $row->warehouse_id,
                        'commerce_platform' => '',
                    ];
                });
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            TiktokShopSync::query()
                ->where('warehouse_id', $warehouseId)
                ->orderByDesc('id')
                ->get()
                ->each(function (TiktokShopSync $row) use (&$targets): void {
                    $targets[] = [
                        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
                        'shop_ref' => (string) ($row->shop_ref ?? ''),
                        'shop_cipher' => (string) ($row->shop_cipher ?? ''),
                        'platform_location_id' => '',
                        'platform_warehouse_id' => (string) ($row->platform_warehouse_id ?? ''),
                        'warehouse_id' => (int) $row->warehouse_id,
                        'commerce_platform' => '',
                    ];
                });
        }

        return $targets;
    }

    /**
     * All mapped warehouses for a Shopee shop (pilot return correction on origin shop only).
     *
     * @return list<array{channel: string, shop_ref: string, shop_cipher: string, platform_location_id: string, platform_warehouse_id: string, warehouse_id: int, commerce_platform: string}>
     */
    public function shopeeTargetsForShop(?int $shopId): array
    {
        $shopRef = $shopId !== null && $shopId > 0 ? (string) $shopId : '';
        if ($shopRef === '') {
            return [];
        }

        $targets = [];

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            MarketplaceWarehouseSync::query()
                ->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE)
                ->where('shop_ref', $shopRef)
                ->where('warehouse_id', '>', 0)
                ->orderBy('id')
                ->get()
                ->each(function (MarketplaceWarehouseSync $row) use (&$targets): void {
                    $targets[] = [
                        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                        'shop_ref' => (string) $row->shop_ref,
                        'shop_cipher' => (string) ($row->shop_cipher ?? ''),
                        'platform_location_id' => (string) ($row->platform_location_id ?? ''),
                        'platform_warehouse_id' => (string) ($row->platform_warehouse_id ?? ''),
                        'warehouse_id' => (int) $row->warehouse_id,
                        'commerce_platform' => '',
                    ];
                });
        }

        if ($targets !== []) {
            return $targets;
        }

        if (Schema::hasTable('shopee_syncs')) {
            Shopeesync::query()
                ->where('shop_id', $shopId)
                ->where('warehouse_id', '>', 0)
                ->orderByDesc('id')
                ->get()
                ->each(function (Shopeesync $row) use ($shopRef, &$targets): void {
                    $targets[] = [
                        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                        'shop_ref' => $shopRef,
                        'shop_cipher' => '',
                        'platform_location_id' => strtoupper(trim((string) ($row->shopee_location_id ?? ''))),
                        'platform_warehouse_id' => (string) ((int) ($row->shopee_warehouse_id ?? 0)),
                        'warehouse_id' => (int) $row->warehouse_id,
                        'commerce_platform' => '',
                    ];
                });
        }

        return $targets;
    }
}