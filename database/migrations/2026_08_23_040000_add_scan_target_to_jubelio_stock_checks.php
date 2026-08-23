<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelio_stock_checks')) {
            return;
        }

        Schema::table('jubelio_stock_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelio_stock_checks', 'target_discrepancies')) {
                $table->unsignedSmallInteger('target_discrepancies')->default(50)->after('demand_days');
            }
            if (! Schema::hasColumn('jubelio_stock_checks', 'scan_round')) {
                $table->unsignedSmallInteger('scan_round')->default(0)->after('target_discrepancies');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelio_stock_checks')) {
            return;
        }

        Schema::table('jubelio_stock_checks', function (Blueprint $table) {
            $columns = array_filter(
                ['target_discrepancies', 'scan_round'],
                fn (string $column) => Schema::hasColumn('jubelio_stock_checks', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
