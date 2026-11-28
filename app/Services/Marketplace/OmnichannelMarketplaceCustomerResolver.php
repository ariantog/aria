<?php

namespace App\Services\Marketplace;

use App\Models\Jubeliosync;
use Illuminate\Support\Facades\Schema;

class OmnichannelMarketplaceCustomerResolver
{
    public function customerIdForWarehouse(int $warehouseId): int
    {
        if ($warehouseId <= 0 || ! Schema::hasTable('jubeliosyncs')) {
            return 0;
        }

        $sync = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('customer_id', '>', 0)
            ->whereMappedJubelioLocation()
            ->orderBy('id')
            ->first();

        if ($sync !== null) {
            return (int) $sync->customer_id;
        }

        $fallback = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('customer_id', '>', 0)
            ->orderBy('id')
            ->first();

        return $fallback !== null ? (int) $fallback->customer_id : 0;
    }
}