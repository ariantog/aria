<?php

use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use App\Services\ShopeeAds\ShopeeAdsTelegramNotifier;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('fills to max item ads on daily reset regardless of max per run', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 10,
        'item_replenish_max_per_run' => 6,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 5000000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn(
        collect(range(1, 10))->map(fn ($i) => ['item_id' => 1000 + $i, 'source' => 'recommended'])->all(),
    );
    $api->shouldReceive('createManualProductAd')
        ->times(10)
        ->andReturnUsing(fn ($itemId) => 'camp-'.$itemId);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $result = $engine->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(10)
        ->and(ShopeeAdsItemAd::query()->count())->toBe(10);
});

it('top-ups at most max per run after item increment schedule', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 10,
        'item_replenish_max_per_run' => 4,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 5000000,
    ]);

    foreach (range(1, 6) as $i) {
        ShopeeAdsItemAd::query()->create([
            'campaign_id' => 'existing-'.$i,
            'item_id' => 2000 + $i,
            'budget' => 25000,
            'status' => 'ongoing',
        ]);
    }

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn(
        collect(range(1, 6))->map(fn ($i) => [
            'campaign_id' => 'existing-'.$i,
            'campaign_name' => 'x',
            'budget' => 25000.0,
            'status' => 'ongoing',
            'item_id' => 2000 + $i,
        ])->all(),
    );
    $api->shouldReceive('getRecommendedItems')->andReturn([
        ['item_id' => 3001, 'source' => 'recommended'],
        ['item_id' => 3002, 'source' => 'recommended'],
        ['item_id' => 3003, 'source' => 'recommended'],
        ['item_id' => 3004, 'source' => 'recommended'],
        ['item_id' => 3005, 'source' => 'recommended'],
    ]);
    $api->shouldReceive('createManualProductAd')
        ->times(4)
        ->andReturnUsing(fn ($itemId) => 'new-'.$itemId);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $result = $engine->replenishItemAds($settings, fillToCap: false);

    expect($result['created'])->toBe(4)
        ->and(ShopeeAdsItemAd::query()->count())->toBe(10);
});

it('runs increment then top-up on produk_manual schedule tick', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 07:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'status' => 'active',
        'max_item_ads' => 10,
        'item_replenish_max_per_run' => 4,
        'item_ad_starting_budget' => 100000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 5000000,
        'item_split_high' => 100,
        'item_split_mid' => 0,
        'item_split_low' => 0,
        'item_roas_off_threshold' => 99,
        'item_off_after_checks' => 99,
    ]);

    foreach (range(1, 6) as $i) {
        ShopeeAdsItemAd::query()->create([
            'campaign_id' => 'existing-'.$i,
            'item_id' => 2000 + $i,
            'budget' => 25000,
            'status' => 'ongoing',
        ]);
    }

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'iklan_produk_manual',
        'run_time' => '07:00',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn(
        collect(range(1, 6))->map(fn ($i) => [
            'campaign_id' => 'existing-'.$i,
            'campaign_name' => 'x',
            'budget' => 25000.0,
            'status' => 'ongoing',
            'item_id' => 2000 + $i,
        ])->all(),
    );
    $api->shouldReceive('getItemAdsRoas')->andReturn([]);
    $api->shouldReceive('addItemAdBudget')->andReturn([
        'before' => 25000,
        'after' => 30000,
        'applied_increment' => 5000,
    ]);
    $api->shouldReceive('getRecommendedItems')->andReturn([
        ['item_id' => 3001, 'source' => 'recommended'],
        ['item_id' => 3002, 'source' => 'recommended'],
        ['item_id' => 3003, 'source' => 'recommended'],
        ['item_id' => 3004, 'source' => 'recommended'],
    ]);
    $api->shouldReceive('createManualProductAd')
        ->times(4)
        ->andReturnUsing(fn ($itemId) => 'new-'.$itemId);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDueSchedules())->toBe(1)
        ->and(ShopeeAdsItemAd::query()->count())->toBe(10);
});
