<?php

use App\Models\ScheduledTask;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->updateOrCreate(
            ['command' => 'shopee:poll-orders'],
            [
                'name' => 'Shopee Poll Orders (direct ingest)',
                'frequency' => 'everyFiveMinutes',
                'active' => false,
                'description' => 'Polls Shopee get_order_list for shopee_syncs rows with direct_order_ingest (e.g. FLEXI Malaysia export shop). Enable SHOPEE_ORDER_POLL_ENABLED and cron task when ready.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'shopee:poll-orders')->delete();
    }
};