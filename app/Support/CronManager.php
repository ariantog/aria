<?php

namespace App\Support;

/**
 * Cron Manager commands handled by Laravel's scheduler directly (not the dispatcher).
 * These are typically long-running and must not run inside app:dispatch-scheduled-tasks.
 */
class CronManager
{
    /**
     * Artisan commands that sync items.qty from warehouse_item (read-only on warehouse rows).
     * Manual maintainer ops only — never register in Cron Manager.
     *
     * @return list<string>
     */
    public static function manualOnlyItemsQtySyncCommands(): array
    {
        return [
            'inventory:recalculate',
            'report:recalculate',
            'app:backfill-items-qty',
            'app:reset-reports',
        ];
    }

    /** @deprecated Use manualOnlyItemsQtySyncCommands() */
    public static function manualOnlyStockCommands(): array
    {
        return self::manualOnlyItemsQtySyncCommands();
    }

    /**
     * Jubelio cron commands allowed to change stock (via SELL/RETURN transaction posting).
     *
     * @return list<string>
     */
    public static function jubelioStockCronCommands(): array
    {
        return [
            'jubelio:order-jubelio-to-aria',
        ];
    }

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

    public static function commandBase(string $command): string
    {
        $command = trim($command);

        return explode(' ', $command, 2)[0];
    }

    public static function isManualOnlyStockCommand(string $command): bool
    {
        return in_array(self::commandBase($command), self::manualOnlyItemsQtySyncCommands(), true);
    }

    public static function isJubelioStockCronCommand(string $command): bool
    {
        return in_array(self::commandBase($command), self::jubelioStockCronCommands(), true);
    }

    /**
     * Cron Manager must not run items.qty sync commands (manual maintainer tools only).
     */
    public static function isAllowedInCronManager(string $command): bool
    {
        if (self::isManualOnlyStockCommand($command)) {
            return false;
        }

        return true;
    }

    public static function cronManagerBlockReason(string $command): ?string
    {
        if (! self::isAllowedInCronManager($command)) {
            return 'This command syncs items.qty from warehouse_item and is manual-only. It never modifies warehouse_item. Only Jubelio order processing may change live stock from cron.';
        }

        return null;
    }
}
