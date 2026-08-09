<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_item_monthly_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'sold_value')) {
                $table->decimal('sold_value', 15, 2)->default(0)->after('returned_qty');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'returned_value')) {
                $table->decimal('returned_value', 15, 2)->default(0)->after('sold_value');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'item_type')) {
                $table->unsignedTinyInteger('item_type')->nullable()->after('returned_value');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('item_type')->constrained('item_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'pcode')) {
                $table->string('pcode', 64)->nullable()->after('group_id');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'type_code')) {
                $table->string('type_code', 64)->default('-')->after('pcode');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'warna_code')) {
                $table->string('warna_code', 64)->default('-')->after('type_code');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'size_code')) {
                $table->string('size_code', 64)->default('-')->after('warna_code');
            }
            if (! Schema::hasColumn('warehouse_item_monthly_stats', 'brand')) {
                $table->unsignedTinyInteger('brand')->nullable()->after('size_code');
            }
        });

        if (! Schema::hasTable('product_performance_rollups')) {
            Schema::create('product_performance_rollups', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('period_days');
                $table->string('lens', 20);
                $table->unsignedBigInteger('warehouse_id')->default(0);
                $table->string('grain', 32);
                $table->string('dimension_key', 191);
                $table->unsignedTinyInteger('item_type')->nullable();
                $table->string('label', 255)->nullable();
                $table->decimal('net_qty', 15, 2)->default(0);
                $table->decimal('net_value', 15, 2)->default(0);
                $table->decimal('pct_of_total', 8, 4)->nullable();
                $table->unsignedInteger('rank')->default(0);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['period_days', 'lens', 'warehouse_id', 'grain', 'dimension_key', 'item_type'],
                    'product_perf_rollups_unique'
                );
                $table->index(['period_days', 'lens', 'warehouse_id', 'grain', 'rank'], 'product_perf_rollups_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_performance_rollups');

        Schema::table('warehouse_item_monthly_stats', function (Blueprint $table) {
            $columns = ['sold_value', 'returned_value', 'item_type', 'group_id', 'pcode', 'type_code', 'warna_code', 'size_code', 'brand'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('warehouse_item_monthly_stats', $column)) {
                    if ($column === 'group_id') {
                        $table->dropConstrainedForeignId('group_id');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
