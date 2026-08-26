<?php

use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Models\User;

it('includes item ad starting budget in the end-of-day plan', function () {
    $user = User::factory()->create();

    $settings = ShopeeAdsSetting::current();
    $settings->update(['item_ad_starting_budget' => 30000]);

    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-plan-1',
        'item_id' => 101,
        'origin' => 'bot',
        'budget' => 30000,
        'status' => 'ongoing',
    ]);
    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-plan-2',
        'item_id' => 102,
        'origin' => 'bot',
        'budget' => 30000,
        'status' => 'ongoing',
    ]);

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'iklan_produk_manual',
        'run_time' => '09:00',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);
    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'iklan_produk_manual',
        'run_time' => '12:00',
        'increment_idr' => 30000,
        'enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('shopee-ads.index'))
        ->assertOk()
        ->assertSee('2 ads × Rp 30,000', false)
        ->assertSee('Start Rp 60,000', false)
        ->assertSee('+ increments Rp 80,000', false)
        ->assertSee('→ planned Rp 140,000', false);
});
