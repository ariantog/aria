<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production L10 tables often have NOT NULL columns with no DEFAULT.
 * L12 partial inserts (firstOrCreate, minimal create arrays) then fail with MySQL 1364.
 *
 * Adds safe defaults on MySQL for every such column except primary keys and users.*.
 * Idempotent: skips columns that already have a default.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const EXCLUDED_TABLES = ['users', 'migrations'];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $primaryKeys = $this->primaryKeyColumns();

        $columns = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, EXTRA
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND IS_NULLABLE = 'NO'
              AND COLUMN_DEFAULT IS NULL
              AND EXTRA NOT LIKE '%auto_increment%'
            ORDER BY TABLE_NAME, ORDINAL_POSITION
        ");

        foreach ($columns as $column) {
            if (in_array($column->TABLE_NAME, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            if (isset($primaryKeys[$column->TABLE_NAME.'.'.$column->COLUMN_NAME])) {
                continue;
            }

            $defaultSql = $this->defaultSqlForColumn($column);
            if ($defaultSql === null) {
                continue;
            }

            $this->applyDefault($column, $defaultSql);
        }
    }

    public function down(): void
    {
        // Irreversible on production — defaults are kept.
    }

    /** @return array<string, true> */
    private function primaryKeyColumns(): array
    {
        $rows = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = 'PRIMARY'
        ");

        $map = [];
        foreach ($rows as $row) {
            $map[$row->TABLE_NAME.'.'.$row->COLUMN_NAME] = true;
        }

        return $map;
    }

    private function defaultSqlForColumn(object $column): ?string
    {
        $type = strtolower($column->DATA_TYPE);

        return match (true) {
            in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double', 'bit'], true) => '0',
            in_array($type, ['varchar', 'char', 'varbinary', 'binary'], true) => "''",
            $type === 'enum' => $this->firstEnumDefault($column->COLUMN_TYPE),
            $type === 'date' => "'1970-01-01'",
            in_array($type, ['datetime', 'timestamp'], true) => "'1970-01-01 00:00:00'",
            in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext'], true) => "''",
            in_array($type, ['blob', 'tinyblob', 'mediumblob', 'longblob'], true) => "''",
            default => null,
        };
    }

    private function firstEnumDefault(string $columnType): ?string
    {
        if (! preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
            return null;
        }

        $values = str_getcsv($matches[1], ',', "'", '\\');
        $first = $values[0] ?? null;

        if ($first === null || $first === '') {
            return null;
        }

        return DB::getPdo()->quote($first);
    }

    private function applyDefault(object $column, string $defaultSql): void
    {
        $sql = sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s NOT NULL DEFAULT %s',
            $column->TABLE_NAME,
            $column->COLUMN_NAME,
            $column->COLUMN_TYPE,
            $defaultSql
        );

        try {
            DB::statement($sql);

            return;
        } catch (\Throwable) {
            if (! $this->canRelaxToNullable($column)) {
                return;
            }
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s NULL DEFAULT NULL',
                $column->TABLE_NAME,
                $column->COLUMN_NAME,
                $column->COLUMN_TYPE
            ));
        } catch (\Throwable) {
            // Leave column unchanged if the host MySQL/MariaDB build rejects the ALTER.
        }
    }

    private function canRelaxToNullable(object $column): bool
    {
        return in_array(strtolower($column->DATA_TYPE), [
            'text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob',
        ], true);
    }
};
