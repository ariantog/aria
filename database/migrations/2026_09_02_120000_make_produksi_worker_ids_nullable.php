<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unassigned produksi workers are NULL, not 0.
 *
 * Production L10 stores `qc_id` as INT NOT NULL DEFAULT 0 and `jahit_id` as
 * INT NOT NULL. Split/create persist NULL for an unassigned worker, which
 * MySQL 1048 rejects. Make the worker FKs nullable with DEFAULT NULL, then
 * convert existing 0 sentinels to NULL.
 *
 *   php artisan migrate --path=database/migrations/2026_09_02_120000_make_produksi_worker_ids_nullable.php --force
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = ['jahit_id', 'qc_id', 'pritil_id'];

    public function up(): void
    {
        if (! Schema::hasTable('prod_produksi')) {
            return;
        }

        ProductionMysqlCompat::alterTable('prod_produksi', function () {
            foreach (self::COLUMNS as $column) {
                $this->makeIntegerNullable('prod_produksi', $column);
            }
        });

        foreach (self::COLUMNS as $column) {
            $this->nullOutZeroes('prod_produksi', $column);
        }
    }

    public function down(): void
    {
        // Irreversible on production — existing NULL worker ids must stay valid.
    }

    private function makeIntegerNullable(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (! ProductionMysqlCompat::isMysql()) {
            return;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $row) {
            return;
        }

        $alreadyNullable = $row->IS_NULLABLE === 'YES';
        $defaultIsNull = $row->COLUMN_DEFAULT === null
            || strtoupper((string) $row->COLUMN_DEFAULT) === 'NULL';

        if ($alreadyNullable && $defaultIsNull) {
            return;
        }

        $type = stripos($row->COLUMN_TYPE, 'bigint') !== false
            ? 'INT(11)'
            : $row->COLUMN_TYPE;

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NULL DEFAULT NULL");
    }

    private function nullOutZeroes(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, 0)->update([$column => null]);
    }
};
