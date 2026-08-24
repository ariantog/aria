<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective migration for production databases migrated before 2026-08-21.
 *
 * Deployments bootstrapped with intermediate code versions ended up with:
 *
 * - NOT NULL legacy columns still missing DB-level DEFAULTs, so partial inserts
 *   fail with MySQL 1364 "Field ... doesn't have a default value".
 * - `standalone_invoices.sender_addrbook_id` / `user_id` still BIGINT from the
 *   superseded 2026_08_19_040000 create migration; fresh installs create them
 *   as INT UNSIGNED (they reference legacy INT ids).
 *
 * Re-runs the idempotent defaults + INT-key migrations and aligns the standalone
 * invoice key columns. No-op on a freshly bootstrapped database and on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2026_08_13_120000_add_production_not_null_column_defaults.php')->up();
        (require __DIR__.'/2026_08_13_130000_fix_production_bigint_columns_to_int.php')->up();
        $this->alignStandaloneInvoiceKeyColumns();
    }

    public function down(): void
    {
        // Corrective only — nothing to reverse.
    }

    private function alignStandaloneInvoiceKeyColumns(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable('standalone_invoices')) {
            return;
        }

        foreach (['sender_addrbook_id', 'user_id'] as $column) {
            if (! Schema::hasColumn('standalone_invoices', $column)) {
                continue;
            }

            $row = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['standalone_invoices', $column]
            );

            if (! $row || stripos($row->COLUMN_TYPE, 'bigint') === false) {
                continue;
            }

            $this->dropForeignKeyIfExists('standalone_invoices', "standalone_invoices_{$column}_foreign");

            ProductionMysqlCompat::withRelaxedSqlMode(function () use ($column) {
                DB::statement("ALTER TABLE `standalone_invoices` MODIFY `{$column}` INT UNSIGNED NULL DEFAULT NULL");
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
