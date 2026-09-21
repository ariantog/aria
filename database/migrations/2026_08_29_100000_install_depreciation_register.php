<?php

use App\Support\GreenfieldMysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production already has `depreciation` (legacy L10 register, PK item_id).
 * Fresh SQLite / greenfield creates the table; every environment then gets
 * the guarded L12 columns. Never drop this table — it is listed in old.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('depreciation')) {
            Schema::create('depreciation', function (Blueprint $table) {
                if (GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys()) {
                    $table->unsignedBigInteger('item_id')->primary();
                } else {
                    $table->integer('item_id')->primary();
                }
                $table->integer('value')->default(0);
                $table->date('buy_date');
                $table->decimal('buy_price', 20, 2)->default(0);
                $table->date('expire_date');
                $table->decimal('residual_value', 20, 2)->default(0);
                $table->integer('useful_life_months')->default(0);
                GreenfieldMysqlSchema::legacyReferenceIdColumn($table, 'warehouse_id', false, 0);
                GreenfieldMysqlSchema::legacyReferenceIdColumn($table, 'buy_transaction_id', true);
                $table->string('notes', 255)->default('');
                $table->index('buy_transaction_id', 'dep_buy_trx_idx');
            });

            return;
        }

        Schema::table('depreciation', function (Blueprint $table) {
            if (! Schema::hasColumn('depreciation', 'residual_value')) {
                $table->decimal('residual_value', 20, 2)->default(0);
            }

            if (! Schema::hasColumn('depreciation', 'useful_life_months')) {
                $table->integer('useful_life_months')->default(0);
            }

            if (! Schema::hasColumn('depreciation', 'warehouse_id')) {
                GreenfieldMysqlSchema::legacyReferenceIdColumn($table, 'warehouse_id', false, 0);
            }

            if (! Schema::hasColumn('depreciation', 'buy_transaction_id')) {
                GreenfieldMysqlSchema::legacyReferenceIdColumn($table, 'buy_transaction_id', true);
            }

            if (! Schema::hasColumn('depreciation', 'notes')) {
                $table->string('notes', 255)->default('');
            }
        });

        if (Schema::hasColumn('depreciation', 'buy_transaction_id')
            && ! Schema::hasIndex('depreciation', 'dep_buy_trx_idx')) {
            Schema::table('depreciation', function (Blueprint $table) {
                $table->index('buy_transaction_id', 'dep_buy_trx_idx');
            });
        }
    }

    public function down(): void
    {
        // Legacy production table — never drop it. Added columns stay in place.
    }
};
