<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe FK / nullable changes for restock_cell_histories audit columns.
 */
final class RestockCellHistoryAuditSchema
{
    public static function makeUserIdNullable(): void
    {
        if (! Schema::hasTable('restock_cell_histories')
            || ! Schema::hasColumn('restock_cell_histories', 'user_id')) {
            return;
        }

        if (self::columnIsNullable('restock_cell_histories', 'user_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            self::dropForeignKeyIfExists('restock_cell_histories', 'restock_cell_histories_user_id_foreign');

            $columnType = self::mysqlColumnType('restock_cell_histories', 'user_id');
            if ($columnType !== null) {
                DB::statement(sprintf(
                    'ALTER TABLE `restock_cell_histories` MODIFY `user_id` %s NULL',
                    $columnType,
                ));
            }

            if (! self::foreignKeyExists('restock_cell_histories', 'restock_cell_histories_user_id_foreign')) {
                Schema::table('restock_cell_histories', function (Blueprint $table) {
                    GreenfieldMysqlSchema::addUserForeignKey(
                        $table,
                        'user_id',
                        true,
                        'restock_cell_histories_user_id_foreign',
                    );
                });
            }

            return;
        }

        Schema::table('restock_cell_histories', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable) {
                //
            }
        });

        Schema::table('restock_cell_histories', function (Blueprint $table) {
            if (GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys()) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            } else {
                $table->integer('user_id')->nullable()->change();
            }
        });

        Schema::table('restock_cell_histories', function (Blueprint $table) {
            GreenfieldMysqlSchema::addUserForeignKey($table, 'user_id', true, 'restock_cell_histories_user_id_foreign');
        });
    }

    public static function relaxRestockCellForeignKey(): void
    {
        if (! Schema::hasTable('restock_cell_histories')
            || ! Schema::hasColumn('restock_cell_histories', 'restock_cell_id')) {
            return;
        }

        if (self::foreignKeyExists('restock_cell_histories', 'restock_cell_hist_cell_fk')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            self::dropForeignKeyIfExists('restock_cell_histories', 'restock_cell_histories_restock_cell_id_foreign');
            self::dropForeignKeyIfExists('restock_cell_histories', 'restock_cell_hist_cell_fk');

            $columnType = self::mysqlColumnType('restock_cell_histories', 'restock_cell_id') ?? 'bigint unsigned';
            DB::statement(sprintf(
                'ALTER TABLE `restock_cell_histories` MODIFY `restock_cell_id` %s NULL',
                $columnType,
            ));
        } else {
            Schema::table('restock_cell_histories', function (Blueprint $table) {
                try {
                    $table->dropForeign(['restock_cell_id']);
                } catch (\Throwable) {
                    //
                }
            });

            Schema::table('restock_cell_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('restock_cell_id')->nullable()->change();
            });
        }

        if (! self::foreignKeyExists('restock_cell_histories', 'restock_cell_hist_cell_fk')) {
            Schema::table('restock_cell_histories', function (Blueprint $table) {
                $table->foreign('restock_cell_id', 'restock_cell_hist_cell_fk')
                    ->references('id')
                    ->on('restock_cells')
                    ->nullOnDelete();
            });
        }
    }

    public static function mysqlColumnType(string $table, string $column): ?string
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row?->COLUMN_TYPE ?? null;
    }

    public static function columnIsNullable(string $table, string $column): bool
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $row = DB::selectOne(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            return ($row?->IS_NULLABLE ?? 'NO') === 'YES';
        }

        return false;
    }

    public static function foreignKeyExists(string $table, string $constraint): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        ) !== null;
    }

    public static function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (self::foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
}
