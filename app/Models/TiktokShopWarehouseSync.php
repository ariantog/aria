<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps TikTok Partner platform warehouse_id (per shop) to an Aria gudang.
 */
class TiktokShopWarehouseSync extends Model
{
    protected $table = 'tiktok_shop_warehouse_syncs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id')->withTrashed();
    }
}