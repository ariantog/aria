<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_insight_rankings')) {
            return;
        }

        Schema::table('item_insight_rankings', function (Blueprint $table) {
            if (! Schema::hasColumn('item_insight_rankings', 'stock_qty')) {
                $table->decimal('stock_qty', 15, 2)->default(0)->after('daily_velocity');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'days_of_cover')) {
                $table->decimal('days_of_cover', 12, 2)->nullable()->after('stock_qty');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'sold_ratio')) {
                $table->decimal('sold_ratio', 12, 4)->nullable()->after('days_of_cover');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'last_buy_qty')) {
                $table->decimal('last_buy_qty', 15, 2)->nullable()->after('sold_ratio');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'last_buy_date')) {
                $table->date('last_buy_date')->nullable()->after('last_buy_qty');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'buy_cover_days')) {
                $table->decimal('buy_cover_days', 12, 2)->nullable()->after('last_buy_date');
            }
            if (! Schema::hasColumn('item_insight_rankings', 'alert_detail')) {
                $table->string('alert_detail', 255)->nullable()->after('buy_cover_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_insight_rankings')) {
            return;
        }

        Schema::table('item_insight_rankings', function (Blueprint $table) {
            foreach (['alert_detail', 'buy_cover_days', 'last_buy_date', 'last_buy_qty', 'sold_ratio', 'days_of_cover', 'stock_qty'] as $column) {
                if (Schema::hasColumn('item_insight_rankings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
