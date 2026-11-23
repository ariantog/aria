<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Export / FLEXI Shopee shop → invoice prefix (+ poll ingest flag).
 * Independent of shopee_syncs pickup warehouse mapping.
 */
class ShopeeFlexiShop extends Model
{
    protected $table = 'shopee_flexi_shops';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'direct_order_ingest' => 'boolean',
            'last_order_poll_at' => 'datetime',
        ];
    }
}