<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L10 settings uses `name` as the key (no slug/group/id).
 *
 * Separate migration because align_production_schema may already be recorded
 * as migrated before settings alignment was added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        ProductionMysqlCompat::normalizeZeroDatesOnTable('settings');

        if (! Schema::hasColumn('settings', 'id')) {
            DB::statement('ALTER TABLE `settings` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }

        Schema::table('settings', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('settings', 'slug')) {
                $blueprint->string('slug', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('settings', 'group')) {
                $blueprint->string('group')->nullable()->after('id');
            }
        });

        if (Schema::hasColumn('settings', 'slug')) {
            DB::statement("UPDATE `settings` SET `slug` = `name` WHERE `slug` IS NULL OR `slug` = ''");
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            ProductionMysqlCompat::withRelaxedSqlMode(function () {
                DB::statement('ALTER TABLE `settings` MODIFY `name` VARCHAR(191) NOT NULL');
                DB::statement('ALTER TABLE `settings` MODIFY `value` TEXT NULL');
            });
        }
    }

    public function down(): void
    {
        // Irreversible on production — column adds are kept.
    }
};
