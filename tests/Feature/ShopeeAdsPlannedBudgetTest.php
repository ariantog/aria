<?php

use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Models\User;

it('includes item ad starting budget in the end-of-day plan', function () {
    $user = User::factory()->create();

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'item_ad_starting_budget' => 100000,
        'max_item_ads' => 4,
    ]);

    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-plan-1',
        'item_id' => 101,
        'origin' => 'bot',
        'budget' => 25000,
        'status' => 'ongoing',
    ]);
    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-plan-2',
        'item_id' => 102,
        'origin' => 'bot',
        'budget' => 25000,
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
        ->assertSee('Pool Rp 100,000', false)
        ->assertSee('÷ 4 slots', false)
        ->assertSee('Rp 25,000/ad', false)
        ->assertSee('2 aktif → Rp 50,000', false)
        ->assertSee('+ increments Rp 80,000', false)
        ->assertSee('→ planned Rp 130,000', false);
});
