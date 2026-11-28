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
            ['command' => 'marketplace:reconcile-missed-orders'],
            [
                'name' => 'Marketplace reconcile missed push orders',
                'frequency' => 'hourly',
                'active' => false,
                'description' => 'Polls Shopee get_order_list for mapped shopee_syncs shops; ingests orders missing from marketplace_channel_orders. Enable MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED and unified storage before activating.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'marketplace:reconcile-missed-orders')->delete();
    }
};