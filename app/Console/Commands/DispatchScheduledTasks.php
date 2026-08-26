<?php

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchScheduledTasks extends Command
{
    protected $signature = 'app:dispatch-scheduled-tasks';

    protected $description = 'Run due Cron Manager tasks (DB-driven frequencies)';

    public function handle(): int
    {
        $tasks = ScheduledTask::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            Log::info('Scheduled task dispatcher: no active tasks');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            if (! $this->shouldRun($task)) {
                continue;
            }

            Log::info('Scheduled task starting', ['command' => $task->command]);

            try {
                $exitCode = $this->call($task->command);
                $task->update(['last_run_at' => now()]);

                if ($exitCode !== self::SUCCESS) {
                    Log::error('Scheduled task returned non-zero exit code', [
                        'command' => $task->command,
                        'exit_code' => $exitCode,
                    ]);
                } else {
                    Log::info('Scheduled task finished', ['command' => $task->command]);
                }
            } catch (\Throwable $e) {
                Log::error('Scheduled task failed', [
                    'command' => $task->command,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function shouldRun(ScheduledTask $task): bool
    {
        if (! $task->last_run_at) {
            return true;
        }

        $last = $task->last_run_at instanceof Carbon
            ? $task->last_run_at
            : Carbon::parse($task->last_run_at);

        $now = now();

        return match ($task->frequency) {
            'everyMinute' => $last->lte($now->copy()->subMinute()),
            'everyTwoMinutes' => $last->lte($now->copy()->subMinutes(2)),
            'everyFiveMinutes' => $last->lte($now->copy()->subMinutes(5)),
            'everyTenMinutes' => $last->lte($now->copy()->subMinutes(10)),
            'everyThirtyMinutes' => $last->lte($now->copy()->subMinutes(30)),
            'hourly' => $last->lte($now->copy()->subHour()),
            'everyTwoHours' => $last->lte($now->copy()->subHours(2)),
            'everyThreeHours' => $last->lte($now->copy()->subHours(3)),
            'everySixHours' => $last->lte($now->copy()->subHours(6)),
            'daily' => ! $last->isSameDay($now),
            'weekly' => $last->lte($now->copy()->subWeek()),
            'monthly' => $last->lte($now->copy()->subMonth()),
            'quarterly' => $last->lte($now->copy()->subMonths(3)),
            'yearly' => $last->lte($now->copy()->subYear()),
            default => $last->lte($now->copy()->subMinute()),
        };
    }
}
