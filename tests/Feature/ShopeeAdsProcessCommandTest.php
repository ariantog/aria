<?php

use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('runs the engine even when SHOPEE_ADS_ACTIVE env is unset', function () {
    $engine = Mockery::mock(ShopeeAdsEngineService::class);
    $engine->shouldReceive('runDailyResetIfDue')->once()->andReturn(false);
    $engine->shouldReceive('syncGmsCurrentSpend')->once()->andReturn(false);
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
        ->expectsOutputToContain('Schedules ran: 0')
        ->expectsOutputToContain('Diagnostics');
});

it('explains schedule timing in diagnostics', function () {
    \App\Models\ShopeeAdsSchedule::query()->create([
        'ad_type' => 'gmv_max',
        'run_time' => '09:00',
        'increment_idr' => 50000,
        'enabled' => true,
    ]);

    $diag = app(ShopeeAdsEngineService::class)->getRunDiagnostics();

    expect($diag['schedules'])->not->toBeEmpty();
});

it('uses WIB for schedule slots when PHP default timezone is UTC', function () {
    date_default_timezone_set('UTC');
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-26 02:15:00', 'UTC'));

    $diag = app(ShopeeAdsEngineService::class)->getRunDiagnostics();

    expect($diag['current_slot'])->toBe('09:15')
        ->and($diag['automation_timezone'])->toBe('Asia/Jakarta')
        ->and($diag['now_utc'])->toBe('2026-08-26 02:15:00');

    \Carbon\Carbon::setTestNow();
    date_default_timezone_set(config('app.timezone'));
});

it('shows automation blockers when cron task is disabled', function () {
    $user = \App\Models\User::factory()->create();

    \App\Models\ScheduledTask::query()->updateOrCreate(
        ['command' => 'shopee-ads:process'],
        [
            'name' => 'Shopee Ads Process',
            'frequency' => 'everyMinute',
            'active' => false,
            'description' => 'test',
        ],
    );

    $this->actingAs($user)
        ->get(route('shopee-ads.index'))
        ->assertOk()
        ->assertSee('data-testid="shopee-ads-automation-blockers"', false)
        ->assertSee('Cron «Shopee Ads Process» nonaktif', false);
});
