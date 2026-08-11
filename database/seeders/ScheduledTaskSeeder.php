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
        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:recalculate-warehouse-item-stats'],
            [
                'name' => 'Recalculate Warehouse Item Stats',
                'frequency' => 'weekly',
                'is_active' => true,
                'description' => 'Rebuilds monthly per-warehouse SKU sell/return statistics for arrangement reports.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:sync-warehouse-arrangement'],
            [
                'name' => 'Sync Warehouse Arrangement',
                'frequency' => 'daily',
                'is_active' => true,
                'description' => 'Pre-computes arrangement candidates and source warehouse matches for fast report loads.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:sync-product-performance'],
            [
                'name' => 'Sync Product Performance',
                'frequency' => 'daily',
                'is_active' => true,
                'description' => 'Rebuilds product performance rollups for sales contribution and warehouse demand reports.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:order-jubelio-to-aria'],
            [
                'name' => 'Sync Jubelio Orders',
                'frequency' => 'everyMinute',
                'is_active' => true,
                'description' => 'Processes pending Jubelio orders into Aria transactions (one per minute).',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:check-connection'],
            [
                'name' => 'Jubelio Check Connection',
                'frequency' => 'hourly',
                'is_active' => true,
                'description' => 'Refreshes Jubelio token and pings the API to detect auth/connectivity issues.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:poll-missing-orders'],
            [
                'name' => 'Jubelio Poll Missing Orders',
                'frequency' => 'hourly',
                'is_active' => true,
                'description' => 'Polls Jubelio for recent orders missing from Aria (catches failed webhooks).',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:get-orders'],
            [
                'name' => 'Jubelio Get Orders (legacy resume)',
                'frequency' => 'everyMinute',
                'is_active' => false,
                'description' => 'Legacy fallback to resume interrupted manual imports. Prefer the queued sync job from Get Orders UI.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:jubelio-stock-check'],
            [
                'name' => 'Jubelio Stock Check',
                'frequency' => 'everyMinute',
                'is_active' => true,
                'description' => 'Compares Aria vs Jubelio stock per synced warehouse (demand-based SKUs).',
            ]
        );
    }
}
