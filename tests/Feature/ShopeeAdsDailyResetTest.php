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

it('catches up daily reset later the same WIB day when the midnight cron tick was missed', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 08:15:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_reset_hour' => 0,
        'daily_reset_minute' => 1,
        'starting_budget_gmv_max' => 100000,
        'gms_campaign_id' => 'gmv-reset',
        'gms_current_budget' => 450000,
        'item_ad_starting_budget' => 100000,
        'max_item_ads' => 4,
        'item_replenish_enabled' => false,
        'last_daily_reset_at' => Carbon::parse('2026-08-26 00:01:00', 'Asia/Jakarta'),
    ]);

    ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-yesterday',
        'item_id' => 99,
        'budget' => 200000,
        'status' => 'ongoing',
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([
        ['campaign_id' => 'item-yesterday', 'campaign_name' => 'x', 'budget' => 200000.0, 'status' => 'ongoing', 'item_id' => 99],
    ]);
    $api->shouldReceive('setGmsBudget')->once()->with('gmv-reset', 100000)->andReturn(true);
    $api->shouldReceive('setItemAdBudget')->once()->with('item-yesterday', 25000)->andReturn(true);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDailyResetIfDue())->toBeTrue()
        ->and($settings->fresh()->gms_current_budget)->toBe(100000)
        ->and($settings->fresh()->last_daily_reset_at?->timezone('Asia/Jakarta')->isSameDay(Carbon::now('Asia/Jakarta')))->toBeTrue()
        ->and(ShopeeAdsItemAd::find('item-yesterday')->budget)->toBe(25000);
});

it('does not run daily reset again after it already ran today', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 08:15:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_reset_hour' => 0,
        'daily_reset_minute' => 1,
        'gms_campaign_id' => 'gmv-reset',
        'last_daily_reset_at' => Carbon::parse('2026-08-27 00:05:00', 'Asia/Jakarta'),
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldNotReceive('setGmsBudget');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDailyResetIfDue())->toBeFalse();
});

it('runs daily reset before increment schedules on the same process tick', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 00:01:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'status' => 'active',
        'daily_reset_hour' => 0,
        'daily_reset_minute' => 1,
        'starting_budget_gmv_max' => 100000,
        'gms_campaign_id' => 'gmv-order',
        'gms_current_budget' => 400000,
        'daily_max_budget' => 500000,
        'item_replenish_enabled' => false,
        'last_daily_reset_at' => Carbon::parse('2026-08-26 00:01:00', 'Asia/Jakarta'),
    ]);

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'gmv_max',
        'run_time' => '00:01',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $callOrder = [];

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('setGmsBudget')
        ->once()
        ->with('gmv-order', 100000)
        ->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'reset';

            return true;
        });
    $api->shouldReceive('addGmsBudget')
        ->once()
        ->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'increment';

            return ['before' => 100000, 'after' => 150000, 'applied_increment' => 50000];
        });
    $api->shouldReceive('getGmsLiveBudget')->andReturn(null);
    $api->shouldReceive('getGmsCampaign')->andReturn(null);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    $reset = $engine->runDailyResetIfDue();
    $schedules = $engine->runDueSchedules();

    expect($reset)->toBeTrue()
        ->and($schedules)->toBe(1)
        ->and($callOrder)->toBe(['reset', 'increment'])
        ->and($settings->fresh()->gms_current_budget)->toBe(150000);
});

it('replenishes item ads after automated daily reset when enabled', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 00:01:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_reset_hour' => 0,
        'daily_reset_minute' => 1,
        'gms_campaign_id' => 'gmv-reset',
        'max_item_ads' => 3,
        'item_ad_starting_budget' => 75000,
        'item_ads_enabled' => true,
        'item_replenish_enabled' => true,
        'daily_max_budget' => 500000,
        'last_daily_reset_at' => Carbon::parse('2026-08-26 00:01:00', 'Asia/Jakarta'),
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('setGmsBudget')->once()->with('gmv-reset', Mockery::type('int'))->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn([
        ['item_id' => 501, 'source' => 'recommended'],
        ['item_id' => 502, 'source' => 'recommended'],
        ['item_id' => 503, 'source' => 'recommended'],
    ]);
    $api->shouldReceive('createManualProductAd')
        ->times(3)
        ->andReturnUsing(fn ($itemId) => 'camp-'.$itemId);

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDailyResetIfDue())->toBeTrue()
        ->and(ShopeeAdsItemAd::query()->count())->toBe(3);
});

it('does not run daily reset before the configured WIB slot on a new day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-27 00:00:30', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_reset_hour' => 0,
        'daily_reset_minute' => 1,
        'gms_campaign_id' => 'gmv-reset',
        'last_daily_reset_at' => Carbon::parse('2026-08-26 00:01:00', 'Asia/Jakarta'),
    ]);

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldNotReceive('setGmsBudget');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDailyResetIfDue())->toBeFalse();
});
