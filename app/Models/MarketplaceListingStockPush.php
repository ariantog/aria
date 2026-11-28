<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceListingStockPush extends Model
{
    protected $table = 'marketplace_listing_stock_pushes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'requested_qty' => 'integer',
            'http_status' => 'integer',
            'pushed_at' => 'datetime',
        ];
    }
}