<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps one Aria warehouse to one Shopee pickup warehouse / stock location.
 *
 * @see \App\Services\Shopee\WarehouseShopeeStockService
 */
class Shopeesync extends Model
{
    /** @use HasFactory<\Database\Factories\ShopeesyncFactory> */
    use HasFactory;

    protected $table = 'shopee_syncs';

    protected $guarded = [];

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id')->withTrashed();
    }
}
