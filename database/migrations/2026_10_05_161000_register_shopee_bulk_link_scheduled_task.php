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
            ['command' => 'app:process-shopee-bulk-link'],
            [
                'name' => 'Shopee Bulk Link Worker',
                'frequency' => 'everyMinute',
                'active' => true,
                'description' => 'Continues in-progress Shopee bulk link uploads (max 1000 rows per minute per run).',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'app:process-shopee-bulk-link')->delete();
    }
};
