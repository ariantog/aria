<?php

use App\Models\ShopeeAdsItemAd;
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

it('deletes low ROAS item ads instead of pausing them during increment', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'item_ads_enabled' => true,
        'item_roas_off_threshold' => 6,
        'item_off_after_checks' => 2,
        'daily_max_budget' => 500000,
        'item_split_high' => 100,
        'item_split_mid' => 0,
        'item_split_low' => 0,
    ]);

    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'bad-1',
        'item_id' => 9001,
        'budget' => 25000,
        'status' => 'ongoing',
        'low_roas_streak' => 1,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('listManualProductAds')->andReturn([
        ['campaign_id' => 'bad-1', 'campaign_name' => 'x', 'budget' => 25000.0, 'status' => 'ongoing', 'item_id' => 9001],
    ]);
    $api->shouldReceive('getItemAdsDailyPerformance')->andReturn([
        'bad-1' => ['roas' => 0.0, 'spend' => 1000.0],
    ]);
    $api->shouldReceive('deleteItemAd')->once()->with('bad-1')->andReturn(true);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $deleted = $engine->applyItemAdsIncrement($settings, 50000);

    expect($deleted)->toBe(1)
        ->and(ShopeeAdsItemAd::query()->count())->toBe(0);
});

it('enforces max item ads cap by deleting excess live ads on daily reset', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-28 00:05:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'item_ads_enabled' => true,
        'max_item_ads' => 2,
        'item_ad_starting_budget' => 50000,
        'item_roas_off_threshold' => 0,
        'item_off_after_checks' => 99,
        'starting_budget_gmv_max' => 100000,
        'gms_campaign_id' => 'gmv-cap',
    ]);

    foreach (range(1, 4) as $i) {
        ShopeeAdsItemAd::query()->create([
            'campaign_id' => 'cap-'.$i,
            'item_id' => 8000 + $i,
            'budget' => 30000,
            'status' => 'ongoing',
            'last_roas' => $i,
        ]);
    }

    $live = collect(range(1, 4))->map(fn ($i) => [
        'campaign_id' => 'cap-'.$i,
        'campaign_name' => 'x',
        'budget' => 30000.0,
        'status' => 'ongoing',
        'item_id' => 8000 + $i,
    ])->all();

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('setGmsBudget')->once()->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn($live);
    $api->shouldReceive('getItemAdsDailyPerformance')->andReturn([
        'cap-1' => ['roas' => 1.0, 'spend' => 100.0],
        'cap-2' => ['roas' => 2.0, 'spend' => 100.0],
        'cap-3' => ['roas' => 3.0, 'spend' => 100.0],
        'cap-4' => ['roas' => 4.0, 'spend' => 100.0],
    ]);
    $api->shouldReceive('deleteItemAd')->twice()->andReturn(true);
    $api->shouldReceive('setItemAdBudget')->twice()->andReturn(true);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $engine->dailyReset($settings);

    expect(ShopeeAdsItemAd::query()->count())->toBe(2)
        ->and(ShopeeAdsItemPerformanceSnapshot::query()->count())->toBe(2);
});

it('counts live Shopee ads toward cap when replenishing', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 6,
        'item_ad_starting_budget' => 150000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 5000000,
    ]);

    foreach (range(1, 17) as $i) {
        ShopeeAdsItemAd::query()->create([
            'campaign_id' => 'live-'.$i,
            'item_id' => 7000 + $i,
            'budget' => 25000,
            'status' => 'ongoing',
        ]);
    }

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn(
        collect(range(1, 17))->map(fn ($i) => [
            'campaign_id' => 'live-'.$i,
            'campaign_name' => 'x',
            'budget' => 25000.0,
            'status' => 'ongoing',
            'item_id' => 7000 + $i,
        ])->all(),
    );
    $api->shouldNotReceive('createManualProductAd');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $result = $engine->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(0)
        ->and($result['message'])->toContain('exceed cap');
});

it('ranks replenish candidates from performance history snapshots', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00', 'Asia/Jakarta'));

    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 5551,
        'snapshot_date' => '2026-08-27',
        'roas' => 8.5,
        'spend' => 10000,
        'budget' => 25000,
    ]);

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'max_item_ads' => 6,
        'item_ad_starting_budget' => 150000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 5000000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn([]);
    $api->shouldReceive('createManualProductAd')
        ->once()
        ->with(5551, Mockery::type('int'), Mockery::type('float'))
        ->andReturn('new-5551');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $result = $engine->replenishItemAds($settings, fillToCap: true);

    expect($result['created'])->toBe(1);
});

it('returns group ad suggestions without calling create API', function () {
    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 6601,
        'snapshot_date' => now()->toDateString(),
        'roas' => 7.2,
        'spend' => 5000,
        'budget' => 25000,
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn([]);
    $api->shouldNotReceive('createManualProductAd');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $suggestions = $engine->suggestGroupAds(ShopeeAdsSetting::current(), 5);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['item_id'])->toBe(6601)
        ->and($suggestions[0]['source'])->toBe('performance_history');
});
