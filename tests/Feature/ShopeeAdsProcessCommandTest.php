<?php

use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('runs the engine even when SHOPEE_ADS_ACTIVE env is unset', function () {
    $engine = Mockery::mock(ShopeeAdsEngineService::class);
    $engine->shouldReceive('runDueSchedules')->once()->andReturn(0);
    $engine->shouldReceive('runDailyResetIfDue')->once()->andReturn(false);
    $engine->shouldReceive('runItemReplenishIfDue')->once()->andReturn(false);
    $engine->shouldReceive('getRunDiagnostics')->once()->andReturn([
        'now_wib' => '2026-08-26 11:00:00',
        'current_slot' => '11:00',
        'paused' => false,
        'authorized' => true,
        'schedules' => ['Tidak ada jadwal untuk slot 11:00 WIB sekarang.'],
        'daily_reset' => ['daily reset hanya di 00:00 WIB (sekarang 11:00).'],
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
