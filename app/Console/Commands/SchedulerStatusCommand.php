<?php

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use App\Support\CronManager;
use App\Support\SchedulerHealth;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SchedulerStatusCommand extends Command
{
    protected $signature = 'app:scheduler-status
                            {--clear-locks : Release stuck schedule mutex locks in the cache store}';

    protected $description = 'Diagnose Laravel scheduler health, mutex locks, and Cron Manager tasks';

    public function handle(): int
    {
        if ($this->option('clear-locks')) {
            return $this->clearLocks();
        }

        $this->info('Scheduler health');
        $snapshot = SchedulerHealth::snapshot();
        $this->table(
            ['Key', 'Value'],
            collect($snapshot)
                ->except('message')
                ->map(fn ($value, $key) => [$key, $this->formatValue($value)])
                ->values()
                ->all(),
        );
        $this->line($snapshot['message']);

        $this->newLine();
        $this->info('Laravel schedule entries');
        Artisan::call('schedule:list');
        $this->line(Artisan::output());

        $this->newLine();
        $this->info('Schedule mutex locks (cache store: '.config('cache.default').')');
        $locks = $this->scheduleMutexRows();
        if ($locks === []) {
            $this->line('No active schedule mutex locks found.');
        } else {
            $this->table(['Mutex', 'Held'], $locks);
        }

        $this->newLine();
        $this->info('Cron Manager tasks (active)');
        $rows = ScheduledTask::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['command', 'frequency', 'last_run_at'])
            ->map(function (ScheduledTask $task) {
                $via = CronManager::isSchedulerOnly($task->command)
                    ? 'scheduler only'
                    : 'dispatcher';

                return [
                    $task->command,
                    $task->frequency,
                    $task->last_run_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i').' WIB',
                    $via,
                ];
            })
            ->all();

        $this->table(['Command', 'Frequency', 'Last run', 'Runs via'], $rows);

        $this->newLine();
        $this->comment('Tip: if locks are stuck after a killed PHP process, run: php artisan app:scheduler-status --clear-locks');

        return self::SUCCESS;
    }

    private function clearLocks(): int
    {
        $released = 0;

        foreach ($this->scheduleMutexNames() as $mutex) {
            if ($this->releaseMutex($mutex)) {
                $released++;
                $this->line("Released: {$mutex}");
            }
        }

        $this->info("Released {$released} schedule mutex lock(s).");

        try {
            Artisan::call('schedule:clear-cache');
            $this->info('schedule:clear-cache completed.');
        } catch (\Throwable $e) {
            $this->warn('schedule:clear-cache skipped: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function scheduleMutexNames(): array
    {
        $names = [];

        foreach (app(Schedule::class)->events() as $event) {
            $names[] = $event->mutexName();
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function scheduleMutexRows(): array
    {
        $rows = [];

        foreach ($this->scheduleMutexNames() as $mutex) {
            $rows[] = [$mutex, $this->mutexHeld($mutex) ? 'yes' : 'no'];
        }

        return $rows;
    }

    private function mutexHeld(string $mutex): bool
    {
        $store = Cache::store(config('cache.default'));
        $cacheStore = $store->getStore();

        if ($cacheStore instanceof \Illuminate\Contracts\Cache\LockProvider) {
            return ! $cacheStore->lock($mutex, 60)->get(fn () => true);
        }

        return $store->has($mutex);
    }

    private function releaseMutex(string $mutex): bool
    {
        $store = Cache::store(config('cache.default'));
        $cacheStore = $store->getStore();

        if ($cacheStore instanceof \Illuminate\Contracts\Cache\LockProvider) {
            $cacheStore->lock($mutex, 60)->forceRelease();

            return true;
        }

        return $store->forget($mutex);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->timezone('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
