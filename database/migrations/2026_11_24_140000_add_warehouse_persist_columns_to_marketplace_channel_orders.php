<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'platform_location_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->string('platform_location_id', 32)->nullable()->default('')->after('platform_warehouse_id');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'aria_warehouse_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->unsignedInteger('aria_warehouse_id')->default(0)->after('platform_location_id');
                $table->index('aria_warehouse_id', 'mkt_ch_ord_aria_wh_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'aria_warehouse_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropIndex('mkt_ch_ord_aria_wh_idx');
                $table->dropColumn('aria_warehouse_id');
            });
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'platform_location_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('platform_location_id');
            });
        }
    }
};