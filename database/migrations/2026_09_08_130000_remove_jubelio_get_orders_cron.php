<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        DB::table('scheduled_tasks')
            ->where('command', 'jubelio:get-orders')
            ->delete();
    }

    public function down(): void
    {
        // No-op — legacy resume cron removed in favor of SyncJubelioMissingOrders queue job.
    }
};
