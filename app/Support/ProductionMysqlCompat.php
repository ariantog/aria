<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for ALTER/migrate on legacy L10 MySQL production databases.
 *
 * Legacy data uses '0000-00-00' dates and strict mode rejects any ALTER that
 * touches a table containing those values. Normalize once, then relax sql_mode
 * during column modifications.
 */
class ProductionMysqlCompat
{
    public static function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * Normalize invalid zero dates on every base table in the current database.
     */
    public static function normalizeZeroDatesForDatabase(): void
    {
        if (! self::isMysql()) {
            return;
        }

        $tables = DB::select("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
        ");

        foreach ($tables as $table) {
            self::normalizeZeroDatesOnTable($table->TABLE_NAME);
        }
    }

    public static function normalizeZeroDatesOnTable(string $table): void
    {
        if (! self::isMysql() || ! Schema::hasTable($table)) {
            return;
        }

        self::withRelaxedSqlMode(function () use ($table) {
            self::relaxInvalidDateDefaults($table);
            self::nullOutZeroDateValues($table);
        });
    }

    /**
     * Prepare a table then run ALTER statements under relaxed sql_mode.
     */
    public static function alterTable(string $table, callable $callback): void
    {
        if (! self::isMysql()) {
            $callback();

            return;
        }

        self::normalizeZeroDatesOnTable($table);
        self::withRelaxedSqlMode($callback);
    }

    public static function withRelaxedSqlMode(callable $callback): void
    {
        if (! self::isMysql()) {
            $callback();

            return;
        }

        DB::statement('SET @aria_old_sql_mode = @@SESSION.sql_mode');
        DB::statement("SET SESSION sql_mode = 'ALLOW_INVALID_DATES'");

        try {
            $callback();
        } finally {
            DB::statement('SET SESSION sql_mode = @aria_old_sql_mode');
        }
    }

    /**
     * @return list<string>
     */
    public static function zeroDateWhereClauses(string $column): array
    {
        return [
            "`{$column}` = '0000-00-00'",
            "`{$column}` = '0000-00-00 00:00:00'",
            "CAST(`{$column}` AS CHAR) LIKE '0000-00-00%'",
            "(`{$column}` IS NOT NULL AND `{$column}` < '1000-01-01')",
        ];
    }

    public static function zeroDateReplacement(string $dataType, string $isNullable): string
    {
        $dataType = strtolower($dataType);

        if ($isNullable === 'YES' || in_array($dataType, ['timestamp', 'datetime'], true)) {
            return 'NULL';
        }

        return "'1970-01-01'";
    }

    public static function relaxedTemporalDefinition(string $dataType): string
    {
        return match (strtolower($dataType)) {
            'timestamp' => 'TIMESTAMP NULL DEFAULT NULL',
            'datetime' => 'DATETIME NULL DEFAULT NULL',
            'date' => 'DATE NULL DEFAULT NULL',
            'year' => 'YEAR NULL DEFAULT NULL',
            default => strtoupper($dataType).' NULL DEFAULT NULL',
        };
    }

    private static function nullOutZeroDateValues(string $table): void
    {
        $columns = DB::select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND DATA_TYPE IN ('date', 'datetime', 'timestamp', 'year')
        ", [$table]);

        foreach ($columns as $column) {
            $name = $column->COLUMN_NAME;
            $dataType = strtolower($column->DATA_TYPE);

            if ($column->IS_NULLABLE !== 'YES' && in_array($dataType, ['timestamp', 'datetime'], true)) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `%s` %s',
                    $table,
                    $name,
                    self::relaxedTemporalDefinition($dataType)
                ));
            }

            $replacement = self::zeroDateReplacement($dataType, $column->IS_NULLABLE);
            $where = implode(' OR ', self::zeroDateWhereClauses($name));

            DB::statement("
                UPDATE `{$table}`
                SET `{$name}` = {$replacement}
                WHERE {$where}
            ");
        }
    }

    /**
     * Columns with DEFAULT '0000-00-00 ...' break subsequent ALTERs under strict mode.
     */
    private static function relaxInvalidDateDefaults(string $table): void
    {
        $columns = DB::select("
            SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND DATA_TYPE IN ('date', 'datetime', 'timestamp', 'year')
        ", [$table]);

        foreach ($columns as $column) {
            $default = $column->COLUMN_DEFAULT;
            if ($default === null || ! str_contains((string) $default, '0000-00-00')) {
                continue;
            }

            $dataType = strtolower($column->DATA_TYPE);

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s',
                $table,
                $column->COLUMN_NAME,
                self::relaxedTemporalDefinition($dataType)
            ));
        }
    }
}
