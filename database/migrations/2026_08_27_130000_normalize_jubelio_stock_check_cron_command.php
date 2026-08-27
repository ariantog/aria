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
            ->where('command', 'app:jubelio-stock-check --single')
            ->update(['command' => 'app:jubelio-stock-check']);
    }

    public function down(): void
    {
        // No-op — command string normalization is forward-only.
    }
};
