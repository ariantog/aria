<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production L12 cron manager uses `active` + `frequency`; legacy prod clones may
 * still have `is_active` and/or `expression`, which breaks schedule:run silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        ProductionMysqlCompat::alterTable('scheduled_tasks', function () {
            if (Schema::hasColumn('scheduled_tasks', 'expression') && ! Schema::hasColumn('scheduled_tasks', 'frequency')) {
                if (ProductionMysqlCompat::isMysql()) {
                    DB::statement('ALTER TABLE `scheduled_tasks` CHANGE `expression` `frequency` VARCHAR(255) NOT NULL DEFAULT \'0 0 * * *\'');
                } else {
                    Schema::table('scheduled_tasks', function (Blueprint $table) {
                        $table->renameColumn('expression', 'frequency');
                    });
                }
            }

            if (Schema::hasColumn('scheduled_tasks', 'is_active') && ! Schema::hasColumn('scheduled_tasks', 'active')) {
                if (ProductionMysqlCompat::isMysql()) {
                    DB::statement('ALTER TABLE `scheduled_tasks` CHANGE `is_active` `active` TINYINT(1) NOT NULL DEFAULT 1');
                } else {
                    Schema::table('scheduled_tasks', function (Blueprint $table) {
                        $table->renameColumn('is_active', 'active');
                    });
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible on production clones.
    }
};
