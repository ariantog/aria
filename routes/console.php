<?php

use App\Models\ScheduledTask;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    if (Schema::hasTable('scheduled_tasks')) {
        $tasks = ScheduledTask::where('is_active', true)->get();

        foreach ($tasks as $task) {
            $event = Schedule::command($task->command);

            $method = $task->frequency;
            if (method_exists($event, $method)) {
                $event->$method();
            } else {
                // Fallback to cron if it's a raw expression or unknown
                $event->cron($task->frequency);
            }

            $event->onSuccess(fn () => $task->update(['last_run_at' => now()]));
        }
    }
} catch (\Exception $e) {
    // Database may not be available during boot
}
