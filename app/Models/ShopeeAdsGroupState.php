<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsGroupState extends Model
{
    protected $fillable = [
        'campaign_id',
        'increments_today',
        'low_roas_streak',
        'last_roas',
        'turned_off',
    ];

    protected function casts(): array
    {
        return [
            'last_roas' => 'float',
            'turned_off' => 'boolean',
        ];
    }
}
