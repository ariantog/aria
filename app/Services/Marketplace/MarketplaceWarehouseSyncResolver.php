<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve platform warehouse keys → Aria gudang (unified table first, legacy fallback).
 */
class MarketplaceWarehouseSyncResolver
{
    public function ariaWarehouseForShopeePickup(
        ?int $shopId,
        array $pickup,
    ): int {
        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            $hit = $this->matchShopeeUnified($shopId, $pickup);
            if ($hit > 0) {
                return $hit;
            }
        }

        return $this->legacyShopeePickup($pickup);
    }

    public function ariaWarehouseForTikTok(
        ?string $shopRef,
        ?string $platformWarehouseId,
        ?string $commercePlatform = null,
    ): int {
        $shopRef = $this->normalizeShopRef($shopRef);
        $platformWarehouseId = trim((string) $platformWarehouseId);

        if ($shopRef !== '' && $platformWarehouseId !== '' && Schema::hasTable('marketplace_warehouse_syncs')) {
            $hit = $this->matchTikTokUnified($shopRef, $platformWarehouseId, $commercePlatform);
            if ($hit > 0) {
                return $hit;
            }
        }

        return $this->legacyTikTokShop($shopRef);
    }

    /**
     * @param  array{shopee_location_id: string, shopee_warehouse_id: int}  $pickup
     */
    private function matchShopeeUnified(?int $shopId, array $pickup): int
    {
        $locationId = (string) ($pickup['shopee_location_id'] ?? '');
        $platformWarehouseId = (string) ((int) ($pickup['shopee_warehouse_id'] ?? 0));
        $shopRef = $shopId !== null && $shopId > 0 ? (string) $shopId : '';

        $query = MarketplaceWarehouseSync::query()
            ->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE)
            ->where('warehouse_id', '>', 0);

        if ($shopRef !== '') {
            $query->where('shop_ref', $shopRef);
        }

        if ($locationId !== '') {
            $hit = (clone $query)->where('platform_location_id', $locationId)->value('warehouse_id');
            if ($hit) {
                return (int) $hit;
            }
        }

        if ($platformWarehouseId !== '0') {
            $hit = (clone $query)->where('platform_warehouse_id', $platformWarehouseId)->value('warehouse_id');
            if ($hit) {
                return (int) $hit;
            }
        }

        if ($shopRef !== '') {
            $distinct = (clone $query)->where('shop_ref', $shopRef)->distinct()->pluck('warehouse_id');
            if ($distinct->count() === 1) {
                return (int) $distinct->first();
            }
        }

        return 0;
    }

    private function matchTikTokUnified(
        string $shopRef,
        string $platformWarehouseId,
        ?string $commercePlatform,
    ): int {
        $commercePlatform = $this->normalizeCommercePlatform($commercePlatform);

        $base = MarketplaceWarehouseSync::query()
            ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
            ->where('shop_ref', $shopRef)
            ->where('platform_warehouse_id', $platformWarehouseId)
            ->where('warehouse_id', '>', 0);

        if ($commercePlatform !== '') {
            $matched = (clone $base)->where('commerce_platform', $commercePlatform)->value('warehouse_id');
            if ($matched) {
                return (int) $matched;
            }
        }

        $wildcard = (clone $base)->where('commerce_platform', '')->value('warehouse_id');
        if ($wildcard) {
            return (int) $wildcard;
        }

        $distinct = (clone $base)->distinct()->pluck('warehouse_id');
        if ($distinct->count() === 1) {
            return (int) $distinct->first();
        }

        return 0;
    }

    /**
     * @param  array{shopee_location_id: string, shopee_warehouse_id: int}  $pickup
     */
    private function legacyShopeePickup(array $pickup): int
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return 0;
        }

        $query = Shopeesync::query()->where('warehouse_id', '>', 0);
        $locationId = (string) ($pickup['shopee_location_id'] ?? '');
        $warehouseId = (int) ($pickup['shopee_warehouse_id'] ?? 0);

        if ($locationId !== '') {
            $hit = (clone $query)->where('shopee_location_id', $locationId)->value('warehouse_id');
            if ($hit) {
                return (int) $hit;
            }
        }

        if ($warehouseId > 0) {
            $hit = (clone $query)->where('shopee_warehouse_id', $warehouseId)->value('warehouse_id');
            if ($hit) {
                return (int) $hit;
            }
        }

        return 0;
    }

    private function legacyTikTokShop(string $shopRef): int
    {
        if ($shopRef === '' || ! Schema::hasTable('tiktok_shop_syncs')) {
            return 0;
        }

        return (int) (TiktokShopSync::query()
            ->where('shop_ref', $shopRef)
            ->where('warehouse_id', '>', 0)
            ->value('warehouse_id') ?? 0);
    }

    public function normalizeShopRef(?string $shopRef): string
    {
        $shopRef = trim((string) $shopRef);
        if ($shopRef === '') {
            return '';
        }

        if (ctype_digit($shopRef)) {
            return (string) (int) $shopRef;
        }

        return $shopRef;
    }

    protected function normalizeCommercePlatform(?string $commercePlatform): string
    {
        $commercePlatform = strtoupper(trim((string) $commercePlatform));
        if ($commercePlatform === 'TOKOPEDIA') {
            return 'TOKOPEDIA';
        }

        if (in_array($commercePlatform, ['TIKTOK_SHOP', 'TIKTOK', 'TTS'], true)) {
            return 'TIKTOK_SHOP';
        }

        return '';
    }
}