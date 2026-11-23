<?php

namespace App\Services\Marketplace;

use App\Models\Shopeesync;
use Illuminate\Support\Facades\Schema;

/**
 * Map Shopee shop + pickup location → Aria warehouse via shopee_syncs rows.
 */
class ShopeeSyncWarehouseResolver
{
    /**
     * @param  array<string, mixed>  $pushData
     * @param  array<string, mixed>|null  $orderRow
     */
    public function ariaWarehouseIdForIngest(
        ?int $shopId,
        array $pushData = [],
        ?array $orderRow = null,
    ): int
    {
        if ($shopId === null || $shopId <= 0 || ! Schema::hasTable('shopee_syncs')) {
            return 0;
        }

        $pickup = app(ShopeeOrderPickupLocation::class)->extract($pushData, $orderRow);
        if ($pickup !== null) {
            $byLocation = $this->matchPickup($shopId, $pickup);
            if ($byLocation > 0) {
                return $byLocation;
            }
        }

        $rows = Shopeesync::query()
            ->where('shop_id', $shopId)
            ->where('warehouse_id', '>', 0)
            ->orderByDesc('direct_order_ingest')
            ->orderBy('id')
            ->get(['warehouse_id', 'direct_order_ingest']);

        if ($rows->isEmpty()) {
            return 0;
        }

        if ($rows->count() === 1) {
            return (int) $rows->first()->warehouse_id;
        }

        // Several pickup rows share one export shop_id — no location on push yet: leave unset (0) until detail/poll enriches.
        return 0;
    }

    /**
     * @param  array{shopee_location_id: string, shopee_warehouse_id: int}  $pickup
     */
    private function matchPickup(int $shopId, array $pickup): int
    {
        $query = Shopeesync::query()
            ->where('shop_id', $shopId)
            ->where('warehouse_id', '>', 0);

        $locationId = (string) ($pickup['shopee_location_id'] ?? '');
        $warehouseId = (int) ($pickup['shopee_warehouse_id'] ?? 0);

        if ($locationId !== '') {
            $hit = (clone $query)
                ->where('shopee_location_id', $locationId)
                ->orderByDesc('direct_order_ingest')
                ->value('warehouse_id');

            if ($hit !== null && (int) $hit > 0) {
                return (int) $hit;
            }
        }

        if ($warehouseId > 0) {
            $hit = (clone $query)
                ->where('shopee_warehouse_id', $warehouseId)
                ->orderByDesc('direct_order_ingest')
                ->value('warehouse_id');

            if ($hit !== null && (int) $hit > 0) {
                return (int) $hit;
            }
        }

        return 0;
    }
}