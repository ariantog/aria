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
            ['command' => 'app:process-tiktok-bulk-link'],
            [
                'name' => 'TikTok Shop Bulk Link Worker',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Continues in-progress TikTok Shop bulk link uploads (max 1000 rows per minute per run).',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'app:process-tiktok-bulk-link')->delete();
    }
};