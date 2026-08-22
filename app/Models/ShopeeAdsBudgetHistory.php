<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsBudgetHistory extends Model
{
    public $timestamps = false;

    protected $table = 'shopee_ads_budget_history';

    protected $fillable = [
        'ad_type',
        'campaign_id',
        'action',
        'before_budget',
        'after_budget',
        'increment_idr',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
