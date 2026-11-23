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
            ['command' => 'shopee:refresh-oauth'],
            [
                'name' => 'Shopee Refresh OAuth',
                'frequency' => 'hourly',
                'active' => true,
                'description' => 'Proactively refreshes the shared Shopee shop access token (shopee_admin_oauth) before it expires (~4h TTL).',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'shopee:refresh-oauth')->delete();
    }
};