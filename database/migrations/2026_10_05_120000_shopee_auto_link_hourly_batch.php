<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopee auto-link: larger batch (50 SKUs/run in app code), lower cron frequency.
     */
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        DB::table('scheduled_tasks')
            ->where('command', 'app:shopee-auto-link-items')
            ->update([
                'frequency' => 'hourly',
                'description' => 'Searches Shopee product API for unlinked SKUs (exact item_sku / model_sku). Up to 50 SKUs per run, ~200 API calls/hour cap; stock required in Shopee-mapped warehouses.',
            ]);
    }

    public function down(): void
    {
        // Forward-only — adjust in Cron Manager if needed.
    }
};
