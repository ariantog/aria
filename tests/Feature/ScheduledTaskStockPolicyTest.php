<?php

use App\Models\ScheduledTask;
use App\Support\CronManager;
use Database\Seeders\ScheduledTaskSeeder;
use Illuminate\Support\Facades\Artisan;

it('blocks manual-only stock rewrite commands from cron manager dispatch', function () {
    ScheduledTask::create([
        'name' => 'Blocked stock rewrite',
        'command' => 'inventory:recalculate',
        'frequency' => 'everyMinute',
        'active' => true,
        'description' => 'Should never run from cron',
    ]);

    Artisan::call('app:dispatch-scheduled-tasks');

    expect(ScheduledTask::query()->where('command', 'inventory:recalculate')->value('last_run_at'))->toBeNull();
});

it('seeded active crons do not register manual-only stock rewrite commands', function () {
    $this->seed(ScheduledTaskSeeder::class);

    $activeCommands = ScheduledTask::query()
        ->where('active', true)
        ->pluck('command');

    foreach ($activeCommands as $command) {
        expect(CronManager::isAllowedInCronManager($command))
            ->toBeTrue("Active cron {$command} must not rewrite warehouse stock (Jubelio order worker only).");
    }
});

it('only jubelio order worker is flagged as a stock-writing cron command', function () {
    expect(CronManager::jubelioStockCronCommands())->toBe(['jubelio:order-jubelio-to-aria'])
        ->and(CronManager::isJubelioStockCronCommand('jubelio:order-jubelio-to-aria'))->toBeTrue()
        ->and(CronManager::isJubelioStockCronCommand('app:jubelio-stock-check'))->toBeFalse()
        ->and(CronManager::isManualOnlyStockCommand('app:backfill-items-qty'))->toBeTrue()
        ->and(CronManager::isManualOnlyStockCommand('report:recalculate --months=2'))->toBeTrue();
});
