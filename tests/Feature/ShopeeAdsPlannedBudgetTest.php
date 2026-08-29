<?php

use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Models\User;

it('does not call Shopee API on index page load', function () {
    $user = User::factory()->create();

    $this->mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('getConnectionStatus')->andReturn([
            'configured' => true,
            'has_token' => true,
            'is_expired' => false,
            'shop_id' => 123,
            'last_error' => null,
        ]);
        $mock->shouldReceive('hasShopAuthorization')->andReturn(true);
        $mock->shouldReceive('formatOAuthErrorForUser')->andReturn(null);
        $mock->shouldReceive('getLastOAuthError')->andReturn(null);
        $mock->shouldNotReceive('listManualProductAds');
        $mock->shouldNotReceive('getGmsItemPerformance');
        $mock->shouldNotReceive('getRecommendedItems');
    });

    $this->actingAs($user)
        ->get(route('shopee-ads.index'))
        ->assertOk();
});

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

it('renders item ads sync stats from session without htmlspecialchars error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'item_ads_sync_stats' => [
                'imported' => 1,
                'updated' => 0,
                'closed' => 0,
                'active' => 2,
            ],
        ])
        ->get(route('shopee-ads.index'))
        ->assertOk()
        ->assertSee('Sync terakhir: 2 aktif', false)
        ->assertSee('(1 baru)', false);
});
