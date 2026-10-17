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
            ['command' => 'app:purge-restock-cell-histories'],
            [
                'name' => 'Purge Old Restock Change History',
                'frequency' => 'monthly',
                'active' => true,
                'description' => 'Deletes restock_cell_histories rows older than 6 months (configurable via --months on the command).',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ScheduledTask::query()->where('command', 'app:purge-restock-cell-histories')->delete();
    }
};
