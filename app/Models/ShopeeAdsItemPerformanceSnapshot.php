<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsItemPerformanceSnapshot extends Model
{
    protected $table = 'shopee_ads_item_performance_snapshots';

    protected $fillable = [
        'item_id',
        'campaign_id',
        'snapshot_date',
        'roas',
        'spend',
        'budget',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'snapshot_date' => 'date',
            'roas' => 'float',
            'spend' => 'integer',
            'budget' => 'integer',
        ];
    }
}
