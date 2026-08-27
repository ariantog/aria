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

        if ($isNullable === 'YES') {
            return 'NULL';
        }

        return "'".self::fallbackTemporalValue($dataType)."'";
    }

    public static function fallbackTemporalValue(string $dataType): string
    {
        // '1971-01-01' is inside the TIMESTAMP range in every timezone.
        return in_array(strtolower($dataType), ['timestamp', 'datetime'], true)
            ? '1971-01-01 00:00:00'
            : '1970-01-01';
    }

    public static function isInvalidLegacyDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($value instanceof \DateTimeInterface) {
            $year = (int) $value->format('Y');

            return $year < 1000;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || str_contains($normalized, '0000-00-00') || str_starts_with($normalized, '-')) {
            return true;
        }

        if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $normalized, $matches)) {
            return (int) $matches[1] < 1000;
        }

        return false;
    }

    public static function sanitizeLegacyDateValue(mixed $value, string $dataType = 'date', bool $nullable = false): ?string
    {
        if (self::isInvalidLegacyDate($value)) {
            return $nullable ? null : self::fallbackTemporalValue($dataType);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(strtolower($dataType) === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        $normalized = trim((string) $value);

        return strtolower($dataType) === 'date'
            ? substr($normalized, 0, 10)
            : $normalized;
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
            $where = implode(' OR ', self::zeroDateWhereClauses($name));

            // Leave healthy columns untouched — an unconditional MODIFY would strip
            // DEFAULT current_timestamp() / ON UPDATE clauses the legacy app relies on.
            $hasZeroDates = DB::selectOne("SELECT 1 AS found FROM `{$table}` WHERE {$where} LIMIT 1");
            if (! $hasZeroDates) {
                continue;
            }

            $replacement = self::zeroDateReplacement($column->DATA_TYPE, $column->IS_NULLABLE);

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
