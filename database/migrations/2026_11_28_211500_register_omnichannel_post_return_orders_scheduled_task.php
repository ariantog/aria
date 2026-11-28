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
            ['command' => 'omnichannel:post-shipped-orders --returns'],
            [
                'name' => 'Omnichannel post marketplace returns',
                'frequency' => 'everyFiveMinutes',
                'active' => false,
                'description' => 'Posts RETURN from marketplace_channel_orders when OMNICHANNEL_POST_RETURNS=true (cancelled / TO_RETURN gate). Run with OMNICHANNEL_POST_RETURN_DRY_RUN=true first; disable Jubelio RETURN queue per channel after validation.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'omnichannel:post-shipped-orders --returns')->delete();
    }
};