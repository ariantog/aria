<?php

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use App\Support\CronManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchScheduledTasks extends Command
{
    protected $signature = 'app:dispatch-scheduled-tasks';

    protected $description = 'Run due Cron Manager tasks (DB-driven frequencies)';

    public function handle(): int
    {
        try {
            return $this->dispatchDueTasks();
        } catch (\Throwable $e) {
            Log::error('Scheduled task dispatcher crashed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return self::FAILURE;
        }
    }

    private function dispatchDueTasks(): int
    {
        Log::info('Scheduled task dispatcher starting');

        $tasks = ScheduledTask::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            Log::info('Scheduled task dispatcher: no active tasks');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            if (CronManager::isSchedulerOnly($task->command)) {
                continue;
            }

            if (! $this->shouldRun($task)) {
                continue;
            }

            Log::info('Scheduled task starting', ['command' => $task->command]);

            try {
                $exitCode = $this->runManagedCommand($task->command);

                if ($exitCode === self::SUCCESS) {
                    $task->update(['last_run_at' => now()]);
                    Log::info('Scheduled task finished', ['command' => $task->command]);
                } else {
                    Log::error('Scheduled task returned non-zero exit code', [
                        'command' => $task->command,
                        'exit_code' => $exitCode,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Scheduled task failed', [
                    'command' => $task->command,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        Log::info('Scheduled task dispatcher finished');

        return self::SUCCESS;
    }

  /**
   * Run a Cron Manager command string (name only, no shell flags).
   */
    private function runManagedCommand(string $command): int
    {
        $command = trim($command);

        if ($command === '') {
            return self::FAILURE;
        }

        if (! str_contains($command, ' ')) {
            return $this->call($command);
        }

        $parts = preg_split('/\s+/', $command) ?: [];
        $name = $parts[0];
        $parameters = [];

        foreach (array_slice($parts, 1) as $part) {
            if (str_starts_with($part, '--')) {
                $key = ltrim($part, '-');
                if (str_contains($key, '=')) {
                    [$key, $value] = explode('=', $key, 2);
                    $parameters[$key] = $value;
                } else {
                    $parameters[$key] = true;
                }
            }
        }

        return $this->call($name, $parameters);
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
