<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Greenfield MySQL uses Laravel BIGINT primary keys on legacy-named tables
 * (customers, items, users, …). Production L10 clones use signed INT(11).
 *
 * Install / bootstrap migrations that target production INT FKs must adapt column
 * types (and only add MySQL FKs when types match).
 */
final class GreenfieldMysqlSchema
{
    public static function usesBigintLegacyPrimaryKeys(): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable('customers')) {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['customers', 'id']
        );

        return $row && stripos($row->COLUMN_TYPE, 'bigint') !== false;
    }

    public static function legacyReferenceIdColumn(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?int $default = null,
    ): void {
        if (self::usesBigintLegacyPrimaryKeys()) {
            $definition = $table->unsignedBigInteger($column);
        } else {
            $definition = $table->integer($column);
        }

        if ($nullable) {
            $definition->nullable();
        }

        if ($default !== null) {
            $definition->default($default);
        }
    }

    public static function addCustomerForeignKey(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?string $name = null,
    ): void {
        $fk = $name !== null ? $table->foreign($column, $name) : $table->foreign($column);
        $fk->references('id')->on('customers');
        $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
    }

    public static function addItemForeignKey(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?string $name = null,
    ): void {
        $fk = $name !== null ? $table->foreign($column, $name) : $table->foreign($column);
        $fk->references('id')->on('items');
        $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
    }

    public static function addItemGroupForeignKey(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?string $name = null,
    ): void {
        $fk = $name !== null ? $table->foreign($column, $name) : $table->foreign($column);
        $fk->references('id')->on('item_group');
        $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
    }

    public static function addUserForeignKey(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?string $name = null,
    ): void {
        $fk = $name !== null ? $table->foreign($column, $name) : $table->foreign($column);
        $fk->references('id')->on('users');
        $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
    }

    public static function addTagForeignKey(
        Blueprint $table,
        string $column,
        bool $nullable = false,
        ?string $name = null,
    ): void {
        $fk = $name !== null ? $table->foreign($column, $name) : $table->foreign($column);
        $fk->references('id')->on('tags');
        $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
    }
}
