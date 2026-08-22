<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsSetting extends Model
{
    protected $table = 'shopee_ads_settings';

    protected $fillable = [
        'status',
        'starting_budget',
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
        'toko_auto_campaign_id',
        'toko_manual_campaign_id',
        'produk_auto_campaign_id',
        'last_daily_reset_at',
        'last_replenish_at',
    ];

    protected function casts(): array
    {
        return [
            'group_roas_off_threshold' => 'float',
            'group_replenish_min_roas' => 'float',
            'group_roas_target' => 'float',
            'group_replenish_enabled' => 'boolean',
            'last_daily_reset_at' => 'datetime',
            'last_replenish_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'status' => 'active',
                'starting_budget' => 100000,
                'daily_max_budget' => 500000,
            ]
        );
    }

    public function isPaused(): bool
    {
        return $this->status !== 'active';
    }

    public function campaignIdForType(string $adType): ?string
    {
        return match ($adType) {
            'toko_auto', 'booster' => $this->toko_auto_campaign_id,
            'toko_manual' => $this->toko_manual_campaign_id,
            'produk_auto' => $this->produk_auto_campaign_id,
            default => null,
        };
    }
}
