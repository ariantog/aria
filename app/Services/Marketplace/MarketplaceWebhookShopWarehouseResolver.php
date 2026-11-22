<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Best-effort shop_ref → Aria warehouse mapping for webhook inbox filters.
 *
 * Shopee: shopee_syncs.shop_id (string match on marketplace_webhook_events.shop_ref).
 * TikTok: tiktok_shop_syncs.shop_ref (webhook shop_id).
 */
class MarketplaceWebhookShopWarehouseResolver
{
    /**
     * @return array<int, string> warehouse_id => addrbook name
     */
    public function warehouseOptions(): array
    {
        $warehouseIds = collect();

        if (Schema::hasTable('shopee_syncs')) {
            $warehouseIds = $warehouseIds->merge(
                Shopeesync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id'),
            );
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            $warehouseIds = $warehouseIds->merge(
                TiktokShopSync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id'),
            );
        }

        $warehouseIds = $warehouseIds->unique()->filter(fn ($id) => (int) $id > 0)->values();

        if ($warehouseIds->isEmpty()) {
            return [];
        }

        return Addrbook::query()
            ->whereIn('id', $warehouseIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<string> shop_ref values tied to the warehouse (Shopee + TikTok).
     */
    public function shopRefsForWarehouse(int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return [];
        }

        $refs = [];

        if (Schema::hasTable('shopee_syncs')) {
            $refs = array_merge($refs, Shopeesync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('shop_id', '>', 0)
                ->pluck('shop_id')
                ->map(fn ($id) => (string) $id)
                ->all());
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            $refs = array_merge($refs, TiktokShopSync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('shop_ref', '!=', '')
                ->pluck('shop_ref')
                ->map(fn ($ref) => (string) $ref)
                ->all());
        }

        return collect($refs)->unique()->values()->all();
    }

    /**
     * @return array<string, int> shop_ref => warehouse_id (first row wins per channel table order)
     */
    public function shopRefToWarehouseMap(): array
    {
        $map = [];

        if (Schema::hasTable('shopee_syncs')) {
            Shopeesync::query()
                ->where('warehouse_id', '>', 0)
                ->where('shop_id', '>', 0)
                ->orderBy('id')
                ->get(['shop_id', 'warehouse_id'])
                ->each(function (Shopeesync $row) use (&$map): void {
                    $ref = (string) $row->shop_id;
                    if ($ref !== '' && ! array_key_exists($ref, $map)) {
                        $map[$ref] = (int) $row->warehouse_id;
                    }
                });
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            TiktokShopSync::query()
                ->where('warehouse_id', '>', 0)
                ->where('shop_ref', '!=', '')
                ->orderBy('id')
                ->get(['shop_ref', 'warehouse_id'])
                ->each(function (TiktokShopSync $row) use (&$map): void {
                    $ref = (string) $row->shop_ref;
                    if ($ref !== '' && ! array_key_exists($ref, $map)) {
                        $map[$ref] = (int) $row->warehouse_id;
                    }
                });
        }

        return $map;
    }

    public function warehouseIdForShopRef(?string $shopRef): int
    {
        if (! filled($shopRef)) {
            return 0;
        }

        return (int) ($this->shopRefToWarehouseMap()[(string) $shopRef] ?? 0);
    }

    public function warehouseNameForShopRef(?string $shopRef, array $shopRefToWarehouse, array $warehouseNames): ?string
    {
        if (! filled($shopRef)) {
            return null;
        }

        $warehouseId = $shopRefToWarehouse[$shopRef] ?? null;
        if ($warehouseId === null) {
            return null;
        }

        return $warehouseNames[$warehouseId] ?? null;
    }

    /**
     * @return Collection<int, string> distinct shop_ref for filter dropdown
     */
    public function distinctShopRefsForChannel(?string $channel): Collection
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            return collect();
        }

        $query = \App\Models\MarketplaceWebhookEvent::query()
            ->whereNotNull('shop_ref')
            ->where('shop_ref', '!=', '');

        if (filled($channel) && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        return $query
            ->distinct()
            ->orderBy('shop_ref')
            ->limit(200)
            ->pluck('shop_ref');
    }
}