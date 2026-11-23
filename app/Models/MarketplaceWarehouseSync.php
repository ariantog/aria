<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketplaceWarehouseSync extends Model
{
    public const CHANNEL_SHOPEE = 'shopee';

    public const CHANNEL_TIKTOK = 'tiktok';

    protected $table = 'marketplace_warehouse_syncs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id')->withTrashed();
    }
}