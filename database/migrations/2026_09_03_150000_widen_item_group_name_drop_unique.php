<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production item_group.name is varchar(50) UNIQUE (see database/old.sql).
 * Widen to varchar(255) and drop the global UNIQUE so colorways may share a title.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_group') || ! Schema::hasColumn('item_group', 'name')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if ($this->indexExists('item_group', 'name')) {
                DB::statement('ALTER TABLE `item_group` DROP INDEX `name`');
            }

            DB::statement('ALTER TABLE `item_group` MODIFY `name` VARCHAR(255) NOT NULL');

            if (! $this->indexExists('item_group', 'item_group_name_idx')) {
                DB::statement('ALTER TABLE `item_group` ADD INDEX `item_group_name_idx` (`name`)');
            }

            return;
        }

        if ($driver === 'sqlite') {
            if ($this->indexExists('item_group', 'item_group_name_unique')) {
                DB::statement('DROP INDEX item_group_name_unique');
            }

            // SQLite tests use Laravel's default string() width; no ALTER needed for width.
            if (! $this->indexExists('item_group', 'item_group_name_idx')) {
                DB::statement('CREATE INDEX item_group_name_idx ON item_group (name)');
            }
        }
    }

    public function down(): void
    {
        // item_group is a live production table — do not shrink name or re-add UNIQUE.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?
                 LIMIT 1',
                [$table, $indexName],
            );

            return $rows !== [];
        }

        return false;
    }
};
