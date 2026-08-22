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

    $engine = new ShopeeAdsEngineService($api);

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

    $engine = new ShopeeAdsEngineService($api);
    $engine->applyItemAdsIncrement($settings, 100);

    expect(\App\Models\ShopeeAdsItemAd::find('item-1')->budget)->toBe(350);
});
