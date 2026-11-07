<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_parents')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasColumn('item_parents', 'description')) {
                DB::statement('ALTER TABLE item_parents MODIFY description TEXT NOT NULL');
            }

            if (Schema::hasColumn('item_parents', 'description2')) {
                DB::statement('ALTER TABLE item_parents MODIFY description2 TEXT NOT NULL');
            }

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite tests: no-op; in-memory schema already tolerates long text in most paths.
        }
    }

    public function down(): void
    {
        // Production: maintainer manages column changes manually.
    }
};
