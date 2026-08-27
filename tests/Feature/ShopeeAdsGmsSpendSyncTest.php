<?php

use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('syncs gmv max spend from shopee into settings', function () {
    $settings = ShopeeAdsSetting::current();

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->once()->andReturn(true);
    $api->shouldReceive('getGmsCampaign')->once()->andReturn([
        'campaign_id' => '414665430',
        'roas' => 4.5,
        'expense' => 900_000.49,
    ]);

    $this->app->instance(ShopeeAdsApiService::class, $api);

    $synced = app(ShopeeAdsEngineService::class)->syncGmsCurrentSpend($settings);

    expect($synced)->toBeTrue()
        ->and($settings->fresh()->gms_current_spend)->toBe(900_000)
        ->and($settings->fresh()->gms_campaign_id)->toBe('414665430')
        ->and($settings->fresh()->gms_current_spend_at)->not->toBeNull();
});

it('skips gmv max spend sync when shop is not authorized', function () {
    $settings = ShopeeAdsSetting::current();

    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('hasShopAuthorization')->once()->andReturn(false);
    $api->shouldNotReceive('getGmsCampaign');

    $this->app->instance(ShopeeAdsApiService::class, $api);

    expect(app(ShopeeAdsEngineService::class)->syncGmsCurrentSpend($settings))->toBeFalse();
});

it('runs gmv spend sync on every shopee ads process tick', function () {
    $engine = Mockery::mock(ShopeeAdsEngineService::class);
    $engine->shouldReceive('runDailyResetIfDue')->once()->andReturn(false);
    $engine->shouldReceive('syncGmsCurrentSpend')->once()->andReturn(true);
    $engine->shouldReceive('runDueSchedules')->once()->andReturn(0);
    $engine->shouldReceive('getRunDiagnostics')->once()->andReturn([
        'now_wib' => '2026-08-26 11:00:00',
        'now_utc' => '2026-08-26 04:00:00',
        'current_slot' => '11:00',
        'automation_timezone' => 'Asia/Jakarta',
        'app_timezone' => 'Asia/Jakarta',
        'php_timezone' => 'UTC',
        'paused' => false,
        'authorized' => true,
        'automation_active' => true,
        'settings_status' => 'active',
        'schedules' => ['Belum ada jadwal due di 11:00 WIB'],
        'daily_reset' => ['daily reset due (jadwal 00:01 WIB, catch-up sampai reset tercatat hari ini).'],
        'replenish' => ['item replenish hanya di 02:30 WIB (sekarang 11:00).'],
    ]);

    $this->app->instance(ShopeeAdsEngineService::class, $engine);

    $this->artisan('shopee-ads:process')
        ->assertSuccessful()
        ->expectsOutputToContain('Schedules ran: 0');
});
