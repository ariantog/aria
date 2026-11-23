<?php

namespace App\Services\TikTokShop;

use App\Models\TiktokShopWarehouseSync;
use Illuminate\Support\Carbon;

/**
 * Upsert seller warehouse rows from Partner logistics API (preserves Aria mapping on refresh).
 */
class TikTokShopWarehouseSyncService
{
    public function __construct(
        private TikTokShopOpenApiService $openApi,
    ) {}

    /**
     * @return int Number of platform warehouse rows touched
     */
    public function refreshForShop(string $shopRef, string $shopCipher, string $shopName = ''): int
    {
        $shopRef = trim($shopRef);
        $shopCipher = trim($shopCipher);
        $shopName = trim($shopName);

        if ($shopRef === '' || $shopCipher === '') {
            return 0;
        }

        $warehouses = $this->openApi->listSellerWarehouses($shopCipher);
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

            $existing = TiktokShopWarehouseSync::query()
                ->where('shop_ref', $shopRef)
                ->where('platform_warehouse_id', $platformId)
                ->where('commerce_platform', '')
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'shop_cipher' => $shopCipher,
                    'shop_name' => $shopName !== '' ? $shopName : $existing->shop_name,
                    'platform_warehouse_name' => (string) ($wh['name'] ?? $existing->platform_warehouse_name),
                    'platform_warehouse_type' => (string) ($wh['type'] ?? $existing->platform_warehouse_type),
                    'last_synced_at' => $now,
                ]);
            } else {
                TiktokShopWarehouseSync::query()->create([
                    'shop_ref' => $shopRef,
                    'shop_cipher' => $shopCipher,
                    'shop_name' => $shopName,
                    'platform_warehouse_id' => $platformId,
                    'platform_warehouse_name' => (string) ($wh['name'] ?? ''),
                    'platform_warehouse_type' => (string) ($wh['type'] ?? ''),
                    'commerce_platform' => '',
                    'warehouse_id' => 0,
                    'last_synced_at' => $now,
                ]);
            }

            $count++;
        }

        return $count;
    }
}