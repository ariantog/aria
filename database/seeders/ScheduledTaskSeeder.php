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
            [
                'name' => 'Sync Jubelio Orders',
                'frequency' => 'everyMinute',
                'is_active' => true,
                'description' => 'Processes pending Jubelio orders into Aria transactions.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:get-orders'],
            [
                'name' => 'Jubelio Get Orders (API backfill)',
                'frequency' => 'everyMinute',
                'is_active' => false,
                'description' => 'Pulls Jubelio sales orders by date range to find missing webhooks. Enabled when a Get Orders import is started.',
            ]
        );
    }
}
