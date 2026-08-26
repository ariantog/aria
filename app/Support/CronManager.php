<?php

namespace App\Support;

/**
 * Cron Manager commands handled by Laravel's scheduler directly (not the dispatcher).
 * These are typically long-running and must not run inside app:dispatch-scheduled-tasks.
 */
class CronManager
{
    /**
     * @return list<string>
     */
    public static function schedulerOnlyCommands(): array
    {
        return [
            'app:process-queue',
            'app:dispatch-scheduled-tasks',
            // Already invoked every minute by app:process-queue.
            'app:process-warehouse-arrangement-refresh',
        ];
    }

    public static function isSchedulerOnly(string $command): bool
    {
        return in_array($command, self::schedulerOnlyCommands(), true);
    }
}
