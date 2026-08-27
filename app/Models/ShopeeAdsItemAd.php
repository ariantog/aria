<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsItemAd extends Model
{
    protected $table = 'shopee_ads_item_ads';

    protected $primaryKey = 'campaign_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id',
        'item_id',
        'origin',
        'budget',
        'roas_target',
        'status',
        'increments_today',
        'low_roas_streak',
        'last_roas',
        'turned_off',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'roas_target' => 'float',
            'last_roas' => 'float',
            'turned_off' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return ! $this->turned_off && ! in_array($this->status, ['ended', 'closed', 'berakhir'], true);
    }
}
