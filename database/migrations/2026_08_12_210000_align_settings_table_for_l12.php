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

        $this->fixLegacyZeroDateTimestamps('settings');

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
            DB::statement('ALTER TABLE `settings` MODIFY `name` VARCHAR(191) NOT NULL');
            DB::statement('ALTER TABLE `settings` MODIFY `value` TEXT NULL');
        }
    }

    public function down(): void
    {
        // Irreversible on production — column adds are kept.
    }

    private function fixLegacyZeroDateTimestamps(string $table): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        $columns = array_values(array_filter(
            ['updated_at', 'created_at'],
            fn (string $column) => Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return;
        }

        DB::statement('SET @aria_old_sql_mode = @@SESSION.sql_mode');
        DB::statement("SET SESSION sql_mode = REPLACE(REPLACE(@aria_old_sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");

        try {
            foreach ($columns as $column) {
                DB::statement("
                    UPDATE `{$table}`
                    SET `{$column}` = NULL
                    WHERE `{$column}` = '0000-00-00 00:00:00'
                       OR `{$column}` = '0000-00-00'
                ");
            }

            foreach ($columns as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NULL DEFAULT NULL");
            }
        } finally {
            DB::statement('SET SESSION sql_mode = @aria_old_sql_mode');
        }
    }
};
