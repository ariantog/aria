<?php

use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('uses live budget when incrementing single ad', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'starting_budget' => 100,
        'daily_max_budget' => 500000,
        'toko_manual_campaign_id' => 'campaign-99',
        'status' => 'active',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('addBudget')
        ->once()
        ->with('toko_manual', 'campaign-99', 100, 500000)
        ->andReturn([
            'before' => 250,
            'after' => 350,
            'applied_increment' => 100,
        ]);

    $engine = new ShopeeAdsEngineService($api);

    expect($engine->incrementSingleAd($settings, 'toko_manual', 100))->toBeTrue();
});
