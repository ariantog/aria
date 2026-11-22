<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\Shopeesync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Best-effort shop_ref → Aria warehouse mapping for webhook inbox filters.
 *
 * Shopee: shopee_syncs.shop_id (string match on marketplace_webhook_events.shop_ref).
 * TikTok: no first-class map in v1 — warehouse filter applies only to rows whose shop_ref
 * appears on a shopee_syncs row (usually empty for TikTok shop_id values).
 */
class MarketplaceWebhookShopWarehouseResolver
{
    /**
     * @return array<int, string> warehouse_id => addrbook name
     */
    public function warehouseOptions(): array
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return [];
        }

        $warehouseIds = Shopeesync::query()
            ->where('warehouse_id', '>', 0)
            ->distinct()
            ->pluck('warehouse_id');

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
     * @return list<string> shop_ref values tied to the warehouse (Shopee shop_id strings).
     */
    public function shopRefsForWarehouse(int $warehouseId): array
    {
        if ($warehouseId <= 0 || ! Schema::hasTable('shopee_syncs')) {
            return [];
        }

        return Shopeesync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('shop_id', '>', 0)
            ->pluck('shop_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int> shop_ref => warehouse_id (first shopee_syncs row wins)
     */
    public function shopRefToWarehouseMap(): array
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return [];
        }

        $map = [];

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

        return $map;
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