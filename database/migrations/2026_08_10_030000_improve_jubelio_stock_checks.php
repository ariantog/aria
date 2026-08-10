<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jubelio_stock_checks')) {
            Schema::table('jubelio_stock_checks', function (Blueprint $table) {
                if (! Schema::hasColumn('jubelio_stock_checks', 'sync_cursor')) {
                    $table->unsignedInteger('sync_cursor')->default(0)->after('page_tracking');
                }
                if (! Schema::hasColumn('jubelio_stock_checks', 'per_type_limit')) {
                    $table->unsignedSmallInteger('per_type_limit')->default(50)->after('sync_cursor');
                }
                if (! Schema::hasColumn('jubelio_stock_checks', 'demand_days')) {
                    $table->unsignedSmallInteger('demand_days')->default(90)->after('per_type_limit');
                }
            });
        }

        if (Schema::hasTable('jubelio_stock_discrepancies')) {
            Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
                if (! Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_on_hand')) {
                    $table->decimal('jubelio_on_hand', 15, 2)->nullable()->after('jubelio_qty');
                }
                if (! Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_on_order')) {
                    $table->decimal('jubelio_on_order', 15, 2)->nullable()->after('jubelio_on_hand');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jubelio_stock_discrepancies')) {
            Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
                $columns = array_filter(
                    ['jubelio_on_hand', 'jubelio_on_order'],
                    fn (string $column) => Schema::hasColumn('jubelio_stock_discrepancies', $column)
                );
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('jubelio_stock_checks')) {
            Schema::table('jubelio_stock_checks', function (Blueprint $table) {
                $columns = array_filter(
                    ['sync_cursor', 'per_type_limit', 'demand_days'],
                    fn (string $column) => Schema::hasColumn('jubelio_stock_checks', $column)
                );
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
