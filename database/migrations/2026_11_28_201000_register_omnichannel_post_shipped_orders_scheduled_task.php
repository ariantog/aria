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
            ['command' => 'omnichannel:post-shipped-orders'],
            [
                'name' => 'Omnichannel post shipped marketplace sells',
                'frequency' => 'everyFiveMinutes',
                'active' => false,
                'description' => 'Posts SELL from marketplace_channel_orders when OMNICHANNEL_POST_SELLS=true (gate via OMNICHANNEL_POST_SELL_GATE). Run with OMNICHANNEL_POST_SELL_DRY_RUN=true first; disable jubelio:order-jubelio-to-aria per channel after shadow validation.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'omnichannel:post-shipped-orders')->delete();
    }
};