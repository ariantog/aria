<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jubelio_stock_checks', function (Blueprint $table) {
            $table->unsignedInteger('sync_cursor')->default(0)->after('page_tracking');
            $table->unsignedSmallInteger('per_type_limit')->default(50)->after('sync_cursor');
            $table->unsignedSmallInteger('demand_days')->default(90)->after('per_type_limit');
        });

        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            $table->decimal('jubelio_on_hand', 15, 2)->nullable()->after('jubelio_qty');
            $table->decimal('jubelio_on_order', 15, 2)->nullable()->after('jubelio_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            $table->dropColumn(['jubelio_on_hand', 'jubelio_on_order']);
        });

        Schema::table('jubelio_stock_checks', function (Blueprint $table) {
            $table->dropColumn(['sync_cursor', 'per_type_limit', 'demand_days']);
        });
    }
};
