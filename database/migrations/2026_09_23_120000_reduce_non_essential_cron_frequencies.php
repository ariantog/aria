<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lower default frequencies for non-essential crons to reduce dispatcher / API load (504 mitigation).
     * Jubelio order posting and queue drain stay every minute.
     */
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        $updates = [
            'app:process-warehouse-arrangement-refresh' => 'everyFiveMinutes',
            'app:backfill-warehouse-item-stats --months=3' => 'everySixHours',
            'jubelio:check-connection' => 'everyThreeHours',
            'jubelio:poll-missing-orders' => 'everyThreeHours',
            'app:jubelio-stock-check' => 'everyFiveMinutes',
            'app:jubelio-auto-link-items' => 'everyFiveMinutes',
            'shopee-ads:process' => 'everyFiveMinutes',
        ];

        foreach ($updates as $command => $frequency) {
            DB::table('scheduled_tasks')
                ->where('command', $command)
                ->update(['frequency' => $frequency]);
        }
    }

    public function down(): void
    {
        // Forward-only — restore via Cron Manager if needed.
    }
};
