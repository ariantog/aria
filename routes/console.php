<?php

use App\Models\ScheduledTask;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    if (Schema::hasTable('scheduled_tasks')) {
        if (! Schema::hasColumn('scheduled_tasks', 'active') && ! Schema::hasColumn('scheduled_tasks', 'is_active')) {
            Log::warning('scheduled_tasks has no active/is_active column; cron schedules were not loaded.');
        } else {
            $tasks = ScheduledTask::activeTasksQuery()->get();

            foreach ($tasks as $task) {
                $event = Schedule::command($task->command);

                $frequency = $task->frequency ?? $task->getAttributes()['expression'] ?? 'daily';
                if (method_exists($event, $frequency)) {
                    $event->$frequency();
                } else {
                    $event->cron($frequency);
                }

                $event->onSuccess(fn () => $task->update(['last_run_at' => now()]));
            }
        }
    }
} catch (\Throwable $e) {
    Log::warning('Failed to load scheduled tasks from database.', [
        'message' => $e->getMessage(),
    ]);
}
