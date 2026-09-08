<?php

use App\Models\ShopeeAdsItemPerformanceSnapshot;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use App\Services\ShopeeAds\ShopeeAdsTelegramNotifier;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function itemCandidateFilterEngine(ShopeeAdsApiService $api): ShopeeAdsEngineService
{
    return new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );
}

it('skips replenish for items with zero ROAS performance history', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-08 06:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 2,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'item_replenish_min_roas' => 6,
        'daily_max_budget' => 5_000_000,
    ]);

    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 5001,
        'snapshot_date' => '2026-09-07',
        'roas' => 0,
        'spend' => 50000,
        'budget' => 100000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getGmsItemPerformance')->andReturn([
        ['item_id' => 5001, 'roas' => 0, 'orders' => 0, 'gmv' => 0],
    ]);
    $api->shouldReceive('getRecommendedItems')->andReturn([
        shopeeRecommendedItem(6001, ['best roi']),
    ]);
    $api->shouldReceive('createManualProductAd')
        ->once()
        ->with(6001, Mockery::type('int'), Mockery::type('float'))
        ->andReturn('camp-good');

    $result = itemCandidateFilterEngine($api)->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(1);
});

it('skips items with recent ad spend and ROAS below minimum even via tag fallback', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-08 06:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 2,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'item_replenish_min_roas' => 6,
        'daily_max_budget' => 5_000_000,
    ]);

    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 7001,
        'snapshot_date' => '2026-09-07',
        'roas' => 2.5,
        'spend' => 30000,
        'budget' => 100000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getGmsItemPerformance')->andReturn([
        ['item_id' => 7001, 'roas' => 2.5, 'orders' => 1, 'gmv' => 50000],
    ]);
    $api->shouldReceive('getRecommendedItems')->andReturn([
        shopeeRecommendedItem(7001, ['best selling']),
        shopeeRecommendedItem(8001, ['best selling']),
    ]);
    $api->shouldReceive('createManualProductAd')
        ->once()
        ->with(8001, Mockery::type('int'), Mockery::type('float'))
        ->andReturn('camp-fresh');

    $result = itemCandidateFilterEngine($api)->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(1);
});

it('creates item ads when GMS ROAS meets minimum replenish threshold', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 1,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'item_replenish_min_roas' => 6,
        'daily_max_budget' => 5_000_000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getGmsItemPerformance')->andReturn([
        ['item_id' => 9001, 'roas' => 7.2, 'orders' => 3, 'gmv' => 180000],
    ]);
    $api->shouldReceive('getRecommendedItems')->andReturn([]);
    $api->shouldReceive('createManualProductAd')
        ->once()
        ->with(9001, Mockery::type('int'), Mockery::type('float'))
        ->andReturn('camp-roas');

    $result = itemCandidateFilterEngine($api)->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(1);
});

it('does not create item ads when only low ROAS GMS candidates exist', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 2,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'item_replenish_min_roas' => 6,
        'daily_max_budget' => 5_000_000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getGmsItemPerformance')->andReturn([
        ['item_id' => 9101, 'roas' => 2.5, 'orders' => 1, 'gmv' => 50000],
        ['item_id' => 9102, 'roas' => 0, 'orders' => 0, 'gmv' => 0],
    ]);
    $api->shouldReceive('getRecommendedItems')->andReturn([]);
    $api->shouldNotReceive('createManualProductAd');

    $result = itemCandidateFilterEngine($api)->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(0);
});
