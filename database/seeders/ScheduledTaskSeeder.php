<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScheduledTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ScheduledTask::where('command', 'queue:work --stop-when-empty --max-time=55')->delete();

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:process-queue'],
            [
                'name' => 'Process Queue Jobs',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Drains the jobs table. Runs via Laravel scheduler directly (not the dispatcher).',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:dispatch-scheduled-tasks'],
            [
                'name' => 'Laravel Scheduler Dispatcher',
                'frequency' => 'everyMinute',
                'active' => false,
                'description' => 'System heartbeat — updated by schedule:run when app:dispatch-scheduled-tasks succeeds. Do not enable here.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:process-warehouse-arrangement-refresh'],
            [
                'name' => 'Process Warehouse Arrangement Refresh',
                'frequency' => 'everyMinute',
                'active' => false,
                'description' => 'Handled by Process Queue Jobs each minute. Kept for manual runs only.',
            ]
        );

        // The unbounded weekly rebuild walked the whole history in one run and
        // exhausted memory on production. Recent months are reconciled daily and the
        // archive is rebuilt in batches instead.
        \App\Models\ScheduledTask::where('command', 'app:recalculate-warehouse-item-stats')->delete();

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:recalculate-warehouse-item-stats --months=2'],
            [
                'name' => 'Reconcile Recent Warehouse Item Stats',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Recomputes the current and previous month from transaction details, correcting any drift left by the live per-transaction updates.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:backfill-warehouse-item-stats --months=3'],
            [
                'name' => 'Backfill Historical Warehouse Item Stats',
                'frequency' => 'hourly',
                'active' => true,
                'description' => 'Rebuilds older months a batch at a time. Idle until a backfill is started from the Warehouse Stats Backfill page.',
            ]
        );

        // Legacy row — deactivate if still present from older seeds.
        \App\Models\ScheduledTask::query()
            ->where('command', 'app:process-warehouse-arrangement-refresh')
            ->where('active', true)
            ->update(['active' => false]);

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:sync-warehouse-arrangement'],
            [
                'name' => 'Sync Warehouse Arrangement',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Pre-computes arrangement candidates and source warehouse matches for fast report loads.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'reporting:snapshot-balances'],
            [
                'name' => 'Snapshot Reporting Balances',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Persists the previous month-end addrbook balances from transaction running balances for historical neraca.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'reporting:rebuild-inventory'],
            [
                'name' => 'Rebuild Persediaan Roll-Forward',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Recomputes company-wide persediaan opening→closing from January 2026 through the current month.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'reporting:rebuild-summaries --months=2'],
            [
                'name' => 'Rebuild Recent Reporting Summaries',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Replays entity, operation, and tax summaries for the current and previous month. Full history stays a manual artisan command.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:sync-product-performance'],
            [
                'name' => 'Sync Product Performance',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Rebuilds product performance rollups for sales contribution and warehouse demand reports.',
            ]
        );

        // Do not cron app:recalculate-inventory-health — it truncates daily_inventory_summaries
        // and walks every year of transaction_details (the same unbounded scan that OOM'd
        // warehouse stats). Inventory Health reads warehouse_item_monthly_stats instead.
        \App\Models\ScheduledTask::where('command', 'app:recalculate-inventory-health')->delete();

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:sync-inventory-health'],
            [
                'name' => 'Sync Inventory Health',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Rebuilds Inventory Health snapshots from warehouse item monthly stats and current stock, one warehouse at a time. Run after the daily warehouse-stats reconcile.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:order-jubelio-to-aria'],
            [
                'name' => 'Sync Jubelio Orders',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Processes pending Jubelio orders into Aria transactions (one per minute).',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:run-monthly-depreciation'],
            [
                'name' => 'Post Monthly Asset Tetap Depreciation',
                'frequency' => 'monthly',
                'active' => false,
                'description' => 'Posts type-18 depreciation for the previous month. Enable after setting akun beban and akumulasi penyusutan.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:process-data-retention-archive'],
            [
                'name' => 'Archive Eligible Transaction Year',
                'frequency' => 'yearly',
                'active' => false,
                'description' => 'Copies the next eligible calendar year to the archive DB. Enable after the archive database is bootstrapped; live cleanup stays manual on Data Retention.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:check-connection'],
            [
                'name' => 'Jubelio Check Connection',
                'frequency' => 'hourly',
                'active' => true,
                'description' => 'Refreshes Jubelio token and pings the API to detect auth/connectivity issues.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:poll-missing-orders'],
            [
                'name' => 'Jubelio Poll Missing Orders',
                'frequency' => 'hourly',
                'active' => true,
                'description' => 'Polls Jubelio for recent orders missing from Aria (catches failed webhooks).',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:get-orders'],
            [
                'name' => 'Jubelio Get Orders (legacy resume)',
                'frequency' => 'everyMinute',
                'active' => false,
                'description' => 'Resumes a running manual Get Orders import one short batch per minute. Enabled automatically while an import is in progress.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:jubelio-stock-check'],
            [
                'name' => 'Jubelio Stock Check',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Compares Aria vs Jubelio available per synced warehouse. One warehouse per cron tick; auto-creates a daily job and scans extra rounds until the target discrepancy count is reached.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'shopee-ads:process'],
            [
                'name' => 'Shopee Ads Process',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Runs Shopee Ads budget schedules, daily reset (WIB), and item ad replenishment.',
            ]
        );
    }
}
