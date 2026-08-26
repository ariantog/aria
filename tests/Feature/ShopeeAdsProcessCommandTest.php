<?php

use App\Services\ShopeeAds\ShopeeAdsEngineService;

it('runs the engine even when SHOPEE_ADS_ACTIVE env is unset', function () {
    $engine = Mockery::mock(ShopeeAdsEngineService::class);
    $engine->shouldReceive('runDueSchedules')->once()->andReturn(0);
    $engine->shouldReceive('runDailyResetIfDue')->once()->andReturn(false);
    $engine->shouldReceive('runItemReplenishIfDue')->once()->andReturn(false);

    $this->app->instance(ShopeeAdsEngineService::class, $engine);

    $this->artisan('shopee-ads:process')
        ->assertSuccessful()
        ->expectsOutputToContain('Schedules ran: 0');
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
