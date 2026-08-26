<?php

use App\Models\ScheduledTask;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Long-running queue drain — own scheduler subprocess (never inside dispatch).
Schedule::command('app:process-queue')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Scheduled task failed', ['command' => 'app:process-queue']);
    });

// Lightweight Cron Manager tasks (shopee-ads:process, Jubelio, etc.).
Schedule::command('app:dispatch-scheduled-tasks')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onFailure(function () {
        Log::error('Scheduled task failed', ['command' => 'app:dispatch-scheduled-tasks']);
    })
    ->onSuccess(function () {
        ScheduledTask::query()
            ->where('command', 'app:dispatch-scheduled-tasks')
            ->update(['last_run_at' => now()]);
    });
