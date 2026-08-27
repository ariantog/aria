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

it('dispatches cron manager commands with CLI flags', function () {
    Log::spy();

    ScheduledTask::query()->updateOrCreate(
        ['command' => 'app:recalculate-warehouse-item-stats --months=2'],
        [
            'name' => 'Reconcile Recent Warehouse Item Stats',
            'frequency' => 'daily',
            'active' => true,
            'description' => 'test',
        ],
    );

    $this->artisan('app:dispatch-scheduled-tasks')
        ->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->with('Scheduled task starting', ['command' => 'app:recalculate-warehouse-item-stats --months=2'])
        ->once();

    Log::shouldHaveReceived('info')
        ->with('Scheduled task finished', ['command' => 'app:recalculate-warehouse-item-stats --months=2'])
        ->once();

    expect(ScheduledTask::query()
        ->where('command', 'app:recalculate-warehouse-item-stats --months=2')
        ->value('last_run_at'))->not->toBeNull();
});

it('does not run scheduler-only commands inside the dispatcher', function () {
    Log::spy();

    ScheduledTask::query()->updateOrCreate(
        ['command' => 'app:process-queue'],
        [
            'name' => 'Process Queue Jobs',
            'frequency' => 'everyMinute',
            'active' => true,
            'description' => 'test',
        ],
    );

    $this->artisan('app:dispatch-scheduled-tasks')
        ->assertSuccessful();

    Log::shouldNotHaveReceived('info', ['Scheduled task starting', ['command' => 'app:process-queue']]);

    expect(ScheduledTask::query()->where('command', 'app:process-queue')->value('last_run_at'))->toBeNull();
});

it('reports scheduler status', function () {
    $this->artisan('app:scheduler-status')
        ->assertSuccessful();
});
