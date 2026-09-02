<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align jubelio_stock_checks / jubelio_stock_discrepancies with the L12 app.
 *
 * Production MySQL still has the L10 shape (`page_tracking` + `status` only).
 * Later L12 migrations added `sync_cursor` and related columns, but those
 * files may already be recorded in `migrations` without the ALTERs applying.
 * This standalone path is safe to run on its own:
 *
 *   php artisan migrate --path=database/migrations/2026_09_01_210000_install_jubelio_stock_check_columns.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->installStockChecksTable();
        $this->installStockDiscrepanciesTable();
    }

    public function down(): void
    {
        // Never drop these tables — they exist on production (database/old.sql).
        $this->dropColumnsIfPresent('jubelio_stock_checks', [
            'sync_cursor',
            'per_type_limit',
            'demand_days',
            'target_discrepancies',
            'scan_round',
        ]);

        $this->dropColumnsIfPresent('jubelio_stock_discrepancies', [
            'jubelio_on_hand',
            'jubelio_on_order',
            'jubelio_available',
            'jubelio_reserved',
        ]);
    }

    private function installStockChecksTable(): void
    {
        if (! Schema::hasTable('jubelio_stock_checks')) {
            Schema::create('jubelio_stock_checks', function (Blueprint $table) {
                $table->id();
                $table->integer('page_tracking')->default(1);
                $table->unsignedInteger('sync_cursor')->default(0);
                $table->unsignedSmallInteger('per_type_limit')->default(50);
                $table->unsignedSmallInteger('demand_days')->default(90);
                $table->unsignedSmallInteger('target_discrepancies')->default(50);
                $table->unsignedSmallInteger('scan_round')->default(0);
                $table->string('status')->default('created');
                $table->timestamps();
            });

            return;
        }

        ProductionMysqlCompat::alterTable('jubelio_stock_checks', function () {
            $this->addUnsignedInteger('jubelio_stock_checks', 'sync_cursor', 0, 'page_tracking');
            $this->addUnsignedSmallInteger('jubelio_stock_checks', 'per_type_limit', 50, 'sync_cursor');
            $this->addUnsignedSmallInteger('jubelio_stock_checks', 'demand_days', 90, 'per_type_limit');
            $this->addUnsignedSmallInteger('jubelio_stock_checks', 'target_discrepancies', 50, 'demand_days');
            $this->addUnsignedSmallInteger('jubelio_stock_checks', 'scan_round', 0, 'target_discrepancies');
        });
    }

    private function installStockDiscrepanciesTable(): void
    {
        if (! Schema::hasTable('jubelio_stock_discrepancies')) {
            Schema::create('jubelio_stock_discrepancies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('jubelio_stock_check_id');
                $table->unsignedBigInteger('jubelio_item_id');
                $table->integer('jubelio_location_id');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('jubelio_location_name')->nullable();
                $table->integer('warehouse_id');
                $table->decimal('aria_qty', 15, 2);
                $table->decimal('jubelio_qty', 15, 2);
                $table->decimal('jubelio_on_hand', 15, 2)->nullable();
                $table->decimal('jubelio_on_order', 15, 2)->nullable();
                $table->decimal('jubelio_available', 15, 2)->nullable();
                $table->decimal('jubelio_reserved', 15, 2)->nullable();
                $table->timestamps();

                $table->index('jubelio_stock_check_id', 'jsc_disc_check_idx');
            });

            return;
        }

        ProductionMysqlCompat::alterTable('jubelio_stock_discrepancies', function () {
            $this->addNullableDecimal('jubelio_stock_discrepancies', 'jubelio_on_hand', 'jubelio_qty');
            $this->addNullableDecimal('jubelio_stock_discrepancies', 'jubelio_on_order', 'jubelio_on_hand');
            $this->addNullableDecimal('jubelio_stock_discrepancies', 'jubelio_available', 'jubelio_on_order');
            $this->addNullableDecimal('jubelio_stock_discrepancies', 'jubelio_reserved', 'jubelio_available');
        });
    }

    private function addUnsignedInteger(string $table, string $column, int $default, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $default, $after) {
            $definition = $blueprint->unsignedInteger($column)->default($default);
            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    private function addUnsignedSmallInteger(string $table, string $column, int $default, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $default, $after) {
            $definition = $blueprint->unsignedSmallInteger($column)->default($default);
            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    private function addNullableDecimal(string $table, string $column, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $after) {
            $definition = $blueprint->decimal($column, 15, 2)->nullable();
            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $present = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($present === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($present) {
            $blueprint->dropColumn($present);
        });
    }
};
