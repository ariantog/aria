<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_channel_orders')
            && ! Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->string('platform_warehouse_id', 64)->nullable()->after('commerce_platform');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('platform_warehouse_id');
            });
        }
    }
};