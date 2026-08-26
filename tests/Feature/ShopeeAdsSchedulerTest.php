<?php

use App\Models\ScheduledTask;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
});

it('dispatches active shopee ads task from cron manager', function () {
    Log::spy();

    ScheduledTask::query()->updateOrCreate(
        ['command' => 'shopee-ads:process'],
        [
            'name' => 'Shopee Ads Process',
            'frequency' => 'everyMinute',
            'active' => true,
            'description' => 'test',
        ],
    );

    $this->artisan('app:dispatch-scheduled-tasks')
        ->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->with('Scheduled task starting', ['command' => 'shopee-ads:process'])
        ->once();

    expect(ScheduledTask::query()->where('command', 'shopee-ads:process')->value('last_run_at'))->not->toBeNull();
});

it('logs and records last run when shopee ads process command runs directly', function () {
    Log::spy();

    ScheduledTask::query()->updateOrCreate(
        ['command' => 'shopee-ads:process'],
        [
            'name' => 'Shopee Ads Process',
            'frequency' => 'everyMinute',
            'active' => true,
            'description' => 'test',
        ],
    );

    $this->artisan('shopee-ads:process')
        ->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->with('Shopee Ads process tick starting')
        ->once();

    expect(ScheduledTask::query()->where('command', 'shopee-ads:process')->value('last_run_at'))->not->toBeNull();
});

it('skips inactive cron manager tasks', function () {
    ScheduledTask::query()->updateOrCreate(
        ['command' => 'shopee-ads:process'],
        [
            'name' => 'Shopee Ads Process',
            'frequency' => 'everyMinute',
            'active' => false,
            'description' => 'test',
        ],
    );

    $this->artisan('app:dispatch-scheduled-tasks')
        ->assertSuccessful();

    expect(ScheduledTask::query()->where('command', 'shopee-ads:process')->value('last_run_at'))->toBeNull();
});
