<?php

use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('uses live budget when incrementing GMV Max', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'starting_budget_gmv_max' => 100,
        'daily_max_budget' => 500000,
        'gms_campaign_id' => 'gmv-1',
        'gms_current_budget' => 100,
        'status' => 'active',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('addGmsBudget')
        ->once()
        ->with('gmv-1', 100, 100, 500000)
        ->andReturn([
            'before' => 250,
            'after' => 350,
            'applied_increment' => 100,
        ]);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->applyGmvMaxIncrement($settings, 100))->toBeTrue();
    expect($settings->fresh()->gms_current_budget)->toBe(350);
});

it('uses live budget when incrementing item ads pool', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_max_budget' => 500000,
        'item_ads_enabled' => true,
        'item_split_high' => 100,
        'item_split_mid' => 0,
        'item_split_low' => 0,
        'item_roas_off_threshold' => 99,
        'item_off_after_checks' => 99,
        'status' => 'active',
    ]);

    \App\Models\ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-1',
        'item_id' => 12345,
        'budget' => 100,
        'status' => 'ongoing',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('listManualProductAds')->andReturn([
        ['campaign_id' => 'item-1', 'campaign_name' => 'x', 'budget' => 250.0, 'status' => 'ongoing', 'item_id' => 12345],
    ]);
    $api->shouldReceive('getItemAdsRoas')->andReturn(['item-1' => 8.0]);
    $api->shouldReceive('addItemAdBudget')
        ->once()
        ->with('item-1', 100, Mockery::type('int'))
        ->andReturn([
            'before' => 250,
            'after' => 350,
            'applied_increment' => 100,
        ]);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );
    $engine->applyItemAdsIncrement($settings, 100);

    expect(\App\Models\ShopeeAdsItemAd::find('item-1')->budget)->toBe(350);
});

it('syncs item ads from Shopee and marks missing campaigns closed', function () {
    \App\Models\ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'stale-1',
        'item_id' => 99,
        'budget' => 50000,
        'status' => 'ongoing',
        'origin' => 'bot',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('listManualProductAds')
        ->once()
        ->with(true)
        ->andReturn([
            [
                'campaign_id' => 'live-1',
                'campaign_name' => 'New',
                'budget' => 100000.0,
                'status' => 'ongoing',
                'item_id' => 12345,
            ],
        ]);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $stats = $engine->syncItemAds();

    expect($stats)->toBe([
        'imported' => 1,
        'updated' => 0,
        'closed' => 1,
        'active' => 1,
    ])
        ->and(\App\Models\ShopeeAdsItemAd::find('live-1')->item_id)->toBe(12345)
        ->and(\App\Models\ShopeeAdsItemAd::find('stale-1')->status)->toBe('closed');
});
