<?php

use App\Models\ScheduledTask;
use Database\Seeders\ScheduledTaskSeeder;

it('seeds reduced frequencies for non-essential crons while keeping jubelio orders every minute', function () {
    $this->seed(ScheduledTaskSeeder::class);

    expect(ScheduledTask::query()->where('command', 'jubelio:order-jubelio-to-aria')->value('frequency'))
        ->toBe('everyMinute')
        ->and(ScheduledTask::query()->where('command', 'app:process-queue')->value('frequency'))
        ->toBe('everyMinute')
        ->and(ScheduledTask::query()->where('command', 'app:jubelio-stock-check')->value('frequency'))
        ->toBe('everyFiveMinutes')
        ->and(ScheduledTask::query()->where('command', 'app:jubelio-auto-link-items')->value('frequency'))
        ->toBe('everyFiveMinutes')
        ->and(ScheduledTask::query()->where('command', 'shopee-ads:process')->value('frequency'))
        ->toBe('everyFiveMinutes')
        ->and(ScheduledTask::query()->where('command', 'jubelio:poll-missing-orders')->value('frequency'))
        ->toBe('everyThreeHours')
        ->and(ScheduledTask::query()->where('command', 'app:backfill-warehouse-item-stats --months=3')->value('frequency'))
        ->toBe('everySixHours');
});
