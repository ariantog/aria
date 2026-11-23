<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Services\Shopee\ShopeeStockApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Carbon;

class MarketplaceWarehouseSyncService
{
    public function __construct(
        private TikTokShopOpenApiService $tiktokOpenApi,
        private ShopeeStockApiService $shopeeStockApi,
        private ShopeeStockOpenApiService $shopeeOpenApi,
    ) {}

    /**
     * @return int Number of platform warehouse rows touched
     */
    public function refreshTikTokShop(string $shopRef, string $shopCipher, string $shopName = ''): int
    {
        $shopRef = trim($shopRef);
        $shopCipher = trim($shopCipher);
        $shopName = trim($shopName);

        if ($shopRef === '' || $shopCipher === '') {
            return 0;
        }

        $warehouses = $this->tiktokOpenApi->listSellerWarehouses($shopCipher);
        if ($warehouses === []) {
            return 0;
        }

        $now = Carbon::now();
        $count = 0;

        foreach ($warehouses as $wh) {
            $platformId = trim((string) ($wh['id'] ?? ''));
            if ($platformId === '') {
                continue;
            }

            $existing = $this->findTikTokRow($shopRef, $platformId, '');

            if ($existing !== null) {
                $existing->update([
                    'shop_cipher' => $shopCipher,
                    'shop_name' => $shopName !== '' ? $shopName : $existing->shop_name,
                    'platform_warehouse_name' => (string) ($wh['name'] ?? $existing->platform_warehouse_name),
                    'platform_warehouse_type' => (string) ($wh['type'] ?? $existing->platform_warehouse_type),
                    'last_synced_at' => $now,
                ]);
            } else {
                MarketplaceWarehouseSync::query()->create([
                    'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
                    'commerce_platform' => '',
                    'shop_ref' => $shopRef,
                    'shop_cipher' => $shopCipher,
                    'shop_name' => $shopName,
                    'platform_location_id' => '',
                    'platform_warehouse_id' => $platformId,
                    'platform_warehouse_name' => (string) ($wh['name'] ?? ''),
                    'platform_warehouse_type' => (string) ($wh['type'] ?? ''),
                    'warehouse_id' => 0,
                    'last_synced_at' => $now,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Manual TikTok warehouse row when Partner logistics API is unavailable.
     */
    public function createManualTikTokWarehouse(
        string $shopRef,
        string $shopCipher,
        string $shopName,
        string $platformWarehouseId,
        string $platformWarehouseName = '',
    ): MarketplaceWarehouseSync {
        $shopRef = trim($shopRef);
        $shopCipher = trim($shopCipher);
        $platformWarehouseId = trim($platformWarehouseId);
        $platformWarehouseName = trim($platformWarehouseName);

        $existing = $this->findTikTokRow($shopRef, $platformWarehouseId, '');

        if ($existing !== null) {
            $existing->update([
                'shop_cipher' => $shopCipher !== '' ? $shopCipher : $existing->shop_cipher,
                'shop_name' => $shopName !== '' ? $shopName : $existing->shop_name,
                'platform_warehouse_name' => $platformWarehouseName !== '' ? $platformWarehouseName : $existing->platform_warehouse_name,
                'meta' => array_merge($existing->meta ?? [], ['manual' => true]),
            ]);

            return $existing->fresh() ?? $existing;
        }

        return MarketplaceWarehouseSync::query()->create([
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'commerce_platform' => '',
            'shop_ref' => $shopRef,
            'shop_cipher' => $shopCipher,
            'shop_name' => trim($shopName),
            'platform_location_id' => '',
            'platform_warehouse_id' => $platformWarehouseId,
            'platform_warehouse_name' => $platformWarehouseName,
            'platform_warehouse_type' => '',
            'warehouse_id' => 0,
            'last_synced_at' => null,
            'meta' => ['manual' => true],
        ]);
    }

    /**
     * Refresh Shopee pickup warehouses for the OAuth-connected shop (single seller token).
     *
     * @return int Number of rows touched
     */
    public function refreshShopeeFromOAuth(): int
    {
        if (! $this->shopeeStockApi->isReady()) {
            return 0;
        }

        $status = $this->shopeeOpenApi->getConnectionStatus();
        $shopRef = (int) ($status['shop_id'] ?? 0) > 0 ? (string) (int) $status['shop_id'] : '';

        $warehouses = $this->shopeeStockApi->listPickupWarehouses();
        if ($warehouses === []) {
            return 0;
        }

        $now = Carbon::now();
        $count = 0;

        foreach ($warehouses as $wh) {
            $platformWarehouseId = (string) ((int) ($wh['warehouse_id'] ?? 0));
            if ($platformWarehouseId === '0') {
                continue;
            }

            $locationId = (string) ($wh['location_id'] ?? '');

            $existing = MarketplaceWarehouseSync::query()
                ->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE)
                ->where('shop_ref', $shopRef)
                ->where('platform_location_id', $locationId)
                ->where('platform_warehouse_id', $platformWarehouseId)
                ->where('commerce_platform', '')
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'platform_warehouse_name' => (string) ($wh['warehouse_name'] ?? $existing->platform_warehouse_name),
                    'last_synced_at' => $now,
                ]);
            } else {
                MarketplaceWarehouseSync::query()->create([
                    'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                    'commerce_platform' => '',
                    'shop_ref' => $shopRef,
                    'shop_cipher' => '',
                    'shop_name' => '',
                    'platform_location_id' => $locationId,
                    'platform_warehouse_id' => $platformWarehouseId,
                    'platform_warehouse_name' => (string) ($wh['warehouse_name'] ?? ''),
                    'warehouse_id' => 0,
                    'last_synced_at' => $now,
                ]);
            }

            $count++;
        }

        return $count;
    }

    public function mirrorShopeeLegacyRow(Shopeesync $legacy): void
    {
        $shopRef = (int) $legacy->shop_id > 0 ? (string) (int) $legacy->shop_id : '';
        $platformWarehouseId = (string) ((int) $legacy->shopee_warehouse_id);
        if ($platformWarehouseId === '0') {
            return;
        }

        MarketplaceWarehouseSync::query()->updateOrCreate(
            [
                'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                'shop_ref' => $shopRef,
                'platform_location_id' => (string) ($legacy->shopee_location_id ?? ''),
                'platform_warehouse_id' => $platformWarehouseId,
                'commerce_platform' => '',
            ],
            [
                'platform_warehouse_name' => (string) ($legacy->shopee_warehouse_name ?? ''),
                'warehouse_id' => (int) $legacy->warehouse_id,
                'meta' => ['legacy_shopee_sync_id' => (int) $legacy->id],
            ],
        );
    }

    public function applyMatrixRowUpdate(MarketplaceWarehouseSync $row, int $warehouseId, string $commercePlatform = ''): void
    {
        $row->update([
            'warehouse_id' => $warehouseId,
            'commerce_platform' => $commercePlatform,
        ]);

        if ($row->channel === MarketplaceWarehouseSync::CHANNEL_SHOPEE) {
            $legacyId = (int) (($row->meta ?? [])['legacy_shopee_sync_id'] ?? 0);
            if ($legacyId > 0) {
                Shopeesync::query()->where('id', $legacyId)->update(['warehouse_id' => $warehouseId]);

                return;
            }

            $legacyQuery = Shopeesync::query()
                ->where('shopee_warehouse_id', (int) $row->platform_warehouse_id)
                ->where('warehouse_id', '>', 0);

            if ($row->shop_ref !== '') {
                $legacyQuery->where('shop_id', (int) $row->shop_ref);
            }

            if ($row->platform_location_id !== '') {
                $legacyQuery->where('shopee_location_id', $row->platform_location_id);
            }

            $legacy = $legacyQuery->first();
            if ($legacy !== null) {
                $legacy->update(['warehouse_id' => $warehouseId]);
            }
        }
    }

    private function findTikTokRow(string $shopRef, string $platformId, string $commercePlatform): ?MarketplaceWarehouseSync
    {
        return MarketplaceWarehouseSync::query()
            ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
            ->where('shop_ref', $shopRef)
            ->where('platform_warehouse_id', $platformId)
            ->where('commerce_platform', $commercePlatform)
            ->first();
    }
}