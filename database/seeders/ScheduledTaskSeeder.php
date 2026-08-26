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
                'description' => 'Drains the jobs table (transaction report aggregates, Jubelio import jobs, etc.).',
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
            ['command' => 'app:sync-product-performance'],
            [
                'name' => 'Sync Product Performance',
                'frequency' => 'daily',
                'active' => true,
                'description' => 'Rebuilds product performance rollups for sales contribution and warehouse demand reports.',
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
                'description' => 'Legacy fallback to resume interrupted manual imports. Prefer the queued sync job from Get Orders UI.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:jubelio-stock-check'],
            [
                'name' => 'Jubelio Stock Check',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Compares Aria vs Jubelio stock per synced warehouse (demand-based SKUs).',
            ]
        );
    }
}
