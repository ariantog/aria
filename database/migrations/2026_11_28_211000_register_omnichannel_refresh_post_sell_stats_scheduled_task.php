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
            ['command' => 'omnichannel:refresh-post-sell-stats'],
            [
                'name' => 'Omnichannel refresh post-sell dashboard stats',
                'frequency' => 'hourly',
                'active' => false,
                'description' => 'Full reconcile of omnichannel_post_sell_stats KPI snapshot (rolling-window drift). Dashboard also updates incrementally on marketplace_channel_orders upserts.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'omnichannel:refresh-post-sell-stats')->delete();
    }
};