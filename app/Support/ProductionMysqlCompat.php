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
            self::nullOutZeroDateValues($table);
            self::relaxInvalidTimestampDefaults($table);
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
        DB::statement("
            SET SESSION sql_mode = REPLACE(
                REPLACE(
                    REPLACE(@aria_old_sql_mode, 'NO_ZERO_DATE', ''),
                    'NO_ZERO_IN_DATE', ''
                ),
                'STRICT_TRANS_TABLES', ''
            )
        ");

        try {
            $callback();
        } finally {
            DB::statement('SET SESSION sql_mode = @aria_old_sql_mode');
        }
    }

    private static function nullOutZeroDateValues(string $table): void
    {
        $columns = DB::select("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND DATA_TYPE IN ('date', 'datetime', 'timestamp')
        ", [$table]);

        foreach ($columns as $column) {
            $name = $column->COLUMN_NAME;
            DB::statement("
                UPDATE `{$table}`
                SET `{$name}` = NULL
                WHERE `{$name}` = '0000-00-00'
                   OR `{$name}` = '0000-00-00 00:00:00'
                   OR `{$name}` LIKE '0000-00-00 %'
            ");
        }
    }

    /**
     * Columns with DEFAULT '0000-00-00 ...' break subsequent ALTERs under strict mode.
     */
    private static function relaxInvalidTimestampDefaults(string $table): void
    {
        $columns = DB::select("
            SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND DATA_TYPE IN ('timestamp', 'datetime')
        ", [$table]);

        foreach ($columns as $column) {
            $default = $column->COLUMN_DEFAULT;
            if ($default === null || ! str_contains((string) $default, '0000-00-00')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s NULL DEFAULT NULL',
                $table,
                $column->COLUMN_NAME,
                $column->COLUMN_TYPE
            ));
        }
    }
}
