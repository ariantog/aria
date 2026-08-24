<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsSetting extends Model
{
    protected $table = 'shopee_ads_settings';

    protected $fillable = [
        'status',
        'starting_budget',
        'starting_budget_gmv_max',
        'daily_max_budget',
        'group_split_high',
        'group_split_mid',
        'group_split_low',
        'group_roas_off_threshold',
        'group_off_after_increments',
        'group_replenish_enabled',
        'group_target_active_count',
        'group_replenish_max_per_run',
        'group_replenish_min_roas',
        'group_roas_target',
        'daily_reset_hour',
        'daily_reset_minute',
        'group_replenish_hour',
        'group_replenish_minute',
        'gms_campaign_id',
        'gms_current_budget',
        'item_ads_enabled',
        'max_item_ads',
        'item_ad_starting_budget',
        'item_replenish_enabled',
        'item_replenish_max_per_run',
        'item_roas_off_threshold',
        'item_off_after_checks',
        'item_new_roas_target',
        'item_split_high',
        'item_split_mid',
        'item_split_low',
        'item_replenish_hour',
        'item_replenish_minute',
        'last_daily_reset_at',
        'last_replenish_at',
        'last_item_replenish_at',
    ];

    protected function casts(): array
    {
        return [
            'group_roas_off_threshold' => 'float',
            'group_replenish_min_roas' => 'float',
            'group_roas_target' => 'float',
            'item_roas_off_threshold' => 'float',
            'item_new_roas_target' => 'float',
            'group_replenish_enabled' => 'boolean',
            'item_ads_enabled' => 'boolean',
            'item_replenish_enabled' => 'boolean',
            'last_daily_reset_at' => 'datetime',
            'last_replenish_at' => 'datetime',
            'last_item_replenish_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'status' => 'active',
                'starting_budget' => 100000,
                'starting_budget_gmv_max' => 100000,
                'daily_max_budget' => 500000,
                'item_ad_starting_budget' => 25000,
            ]
        );
    }

    public function isPaused(): bool
    {
        return $this->status !== 'active';
    }
}
