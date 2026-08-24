<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct BIGINT columns added by earlier align/install runs on production.
 *
 * Production PKs are signed INT(11). References to customers, prod_worker,
 * prod_produksi, and transactions must be INT, not BIGINT UNSIGNED.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'customers' => ['operation_id', 'default_bank_id'],
        'prod_produksi' => ['qc_id', 'pritil_id', 'original_id', 'transaction_id'],
        'product_performance_rollups' => ['warehouse_id'],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            ProductionMysqlCompat::alterTable($table, function () use ($table, $columns) {
                foreach ($columns as $column) {
                    $this->modifyIntIfBigint($table, $column);
                }
            });
        }
    }

    public function down(): void
    {
        // Irreversible — INT is the correct production type.
    }

    private function modifyIntIfBigint(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $row || stripos($row->COLUMN_TYPE, 'bigint') === false) {
            return;
        }

        $nullable = $row->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $this->defaultClause($row, $column);

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT(11) {$nullable}{$default}");
    }

    private function defaultClause(object $row, string $column): string
    {
        $columnDefault = $row->COLUMN_DEFAULT;

        if ($columnDefault !== null && strtoupper((string) $columnDefault) === 'NULL') {
            $columnDefault = null;
        }

        if ($row->IS_NULLABLE === 'YES') {
            return $columnDefault === null ? '' : ' DEFAULT '.(int) $columnDefault;
        }

        if ($columnDefault !== null) {
            return is_numeric($columnDefault)
                ? ' DEFAULT '.(int) $columnDefault
                : " DEFAULT '".$columnDefault."'";
        }

        if ($column === 'warehouse_id') {
            return ' DEFAULT 0';
        }

        return '';
    }
};
