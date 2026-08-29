<?php

use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use App\Services\ShopeeAds\ShopeeAdsTelegramNotifier;

it('divides item ads starting pool across max item ad slots', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'item_ad_starting_budget' => 100000,
        'max_item_ads' => 4,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'item_replenish_max_per_run' => 4,
        'daily_max_budget' => 500000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn([
        shopeeRecommendedItem(101),
        shopeeRecommendedItem(102),
        shopeeRecommendedItem(103),
        shopeeRecommendedItem(104),
    ]);
    $api->shouldReceive('getGmsItemPerformance')->andReturn([]);
    $api->shouldReceive('createManualProductAd')
        ->times(4)
        ->withArgs(fn ($itemId, $budget) => $budget === 25000)
        ->andReturnUsing(fn ($itemId) => 'camp-'.$itemId);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $result = $engine->replenishItemAds($settings);

    expect($result['created'])->toBe(4)
        ->and(ShopeeAdsItemAd::query()->where('budget', 25000)->count())->toBe(4);
});

it('resets each item ad to per-slot budget from the shared pool', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'item_ad_starting_budget' => 100000,
        'max_item_ads' => 4,
        'item_ads_enabled' => true,
    ]);

    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-reset',
        'item_id' => 55,
        'budget' => 100000,
        'status' => 'ongoing',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(false);
    $api->shouldReceive('getGmsCampaign')->andReturn(null);
    $api->shouldReceive('setItemAdBudget')->once()->with('item-reset', 25000)->andReturn(true);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $engine->dailyReset($settings);

    expect(ShopeeAdsItemAd::find('item-reset')->budget)->toBe(25000);
});

it('uses scaled combined cap on double date for headroom', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-08-08 10:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_max_budget' => 500000,
        'double_date_enabled' => true,
        'double_date_gmv_multiplier' => 2,
        'double_date_item_budget_multiplier' => 2,
        'gms_current_budget' => 400000,
    ]);

    $engine = new ShopeeAdsEngineService(
        Mockery::mock(ShopeeAdsApiService::class)->shouldIgnoreMissing(),
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->combinedDailyCap($settings))->toBe(1000000)
        ->and($engine->combinedHeadroom($settings))->toBe(600000);

    Carbon\Carbon::setTestNow();
});
