<?php

namespace App\Services\Marketplace;

use App\Models\Shopeesync;
use Illuminate\Support\Facades\Schema;

/**
 * Map Shopee pickup location → Aria warehouse via shopee_syncs (stock rows only).
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
    ): int {
        if (! Schema::hasTable('shopee_syncs')) {
            return 0;
        }

        $pickup = app(ShopeeOrderPickupLocation::class)->extract($pushData, $orderRow);
        if ($pickup === null) {
            return 0;
        }

        return $this->matchPickup($pickup);
    }

    /**
     * @param  array{shopee_location_id: string, shopee_warehouse_id: int}  $pickup
     */
    private function matchPickup(array $pickup): int
    {
        $query = Shopeesync::query()->where('warehouse_id', '>', 0);

        $locationId = (string) ($pickup['shopee_location_id'] ?? '');
        $warehouseId = (int) ($pickup['shopee_warehouse_id'] ?? 0);

        if ($locationId !== '') {
            $hit = (clone $query)
                ->where('shopee_location_id', $locationId)
                ->value('warehouse_id');

            if ($hit !== null && (int) $hit > 0) {
                return (int) $hit;
            }
        }

        if ($warehouseId > 0) {
            $hit = (clone $query)
                ->where('shopee_warehouse_id', $warehouseId)
                ->value('warehouse_id');

            if ($hit !== null && (int) $hit > 0) {
                return (int) $hit;
            }
        }

        return 0;
    }
}