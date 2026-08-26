<?php

use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use Carbon\Carbon;

it('runs a schedule within the grace window after the slot', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 11:22:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update(['status' => 'active', 'gms_campaign_id' => 'gmv-grace', 'gms_current_budget' => 100000, 'daily_max_budget' => 500000]);

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'gmv_max',
        'run_time' => '11:20',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldReceive('addGmsBudget')
        ->once()
        ->andReturn(['before' => 100000, 'after' => 150000, 'applied_increment' => 50000]);

    $telegram = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class);
    $telegram->shouldIgnoreMissing();

    $engine = new ShopeeAdsEngineService($api, app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class), $telegram);

    expect($engine->runDueSchedules())->toBe(1)
        ->and(ShopeeAdsSchedule::first()->last_run_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('does not run schedules when automation is paused', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 11:20:00', 'Asia/Jakarta'));

    ShopeeAdsSetting::current()->update(['status' => 'paused']);

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'gmv_max',
        'run_time' => '11:20',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldNotReceive('addGmsBudget');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDueSchedules())->toBe(0)
        ->and(ShopeeAdsSchedule::first()->last_run_at)->toBeNull();

    Carbon::setTestNow();
});

it('skips schedule after grace window passes', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 11:36:00', 'Asia/Jakarta'));

    ShopeeAdsSetting::current()->update(['status' => 'active']);

    ShopeeAdsSchedule::query()->create([
        'ad_type' => 'gmv_max',
        'run_time' => '11:20',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->andReturn(true);
    $api->shouldNotReceive('addGmsBudget');

    $engine = new ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );

    expect($engine->runDueSchedules())->toBe(0);

    Carbon::setTestNow();
});
