<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps TikTok Partner webhook shop_ref (shop_id) to one Aria warehouse.
 */
class TiktokShopSync extends Model
{
    /** @use HasFactory<\Database\Factories\TiktokShopSyncFactory> */
    use HasFactory;

    protected $table = 'tiktok_shop_syncs';

    protected $guarded = [];

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id')->withTrashed();
    }
}