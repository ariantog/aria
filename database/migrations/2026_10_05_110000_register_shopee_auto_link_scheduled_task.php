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
            ['command' => 'app:shopee-auto-link-items'],
            [
                'name' => 'Shopee Auto Link Items',
                'frequency' => 'everyFiveMinutes',
                'active' => true,
                'description' => 'Searches Shopee product API for unlinked SKUs (exact item_sku / model_sku). Batched to ~200 API calls/hour; stock required in Shopee-mapped warehouses.',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'app:shopee-auto-link-items')->delete();
    }
};
