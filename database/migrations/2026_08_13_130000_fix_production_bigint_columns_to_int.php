<?php

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
        'customers' => ['operation_id'],
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

            foreach ($columns as $column) {
                $this->modifyIntIfBigint($table, $column);
            }
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
        $default = '';

        if ($row->COLUMN_DEFAULT !== null) {
            $default = is_numeric($row->COLUMN_DEFAULT)
                ? ' DEFAULT '.(int) $row->COLUMN_DEFAULT
                : " DEFAULT '".$row->COLUMN_DEFAULT."'";
        } elseif ($row->IS_NULLABLE === 'NO' && $column === 'warehouse_id') {
            $default = ' DEFAULT 0';
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT(11) {$nullable}{$default}");
    }
};
