<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelioorders', 'warehouse_id')) {
                $table->unsignedInteger('warehouse_id')->default(0)->after('order_status');
                $table->index('warehouse_id', 'jubelioorders_wh_idx');
            }

            if (! Schema::hasColumn('jubelioorders', 'stock_error_items')) {
                $table->json('stock_error_items')->nullable()->after('error');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (Schema::hasColumn('jubelioorders', 'stock_error_items')) {
                $table->dropColumn('stock_error_items');
            }

            if (Schema::hasColumn('jubelioorders', 'warehouse_id')) {
                $table->dropIndex('jubelioorders_wh_idx');
                $table->dropColumn('warehouse_id');
            }
        });
    }
};
