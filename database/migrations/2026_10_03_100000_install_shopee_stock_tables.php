<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_syncs')) {
            Schema::create('shopee_syncs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('warehouse_id');
                $table->unsignedInteger('shop_id')->default(0);
                $table->string('shopee_location_id', 32)->default('');
                $table->unsignedBigInteger('shopee_warehouse_id')->default(0);
                $table->string('shopee_warehouse_name')->default('');
                $table->timestamps();

                $table->index('warehouse_id', 'shopee_sync_wh_idx');
            });
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'shopee_item_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('shopee_item_id')->nullable()->after('jubelio_item_id');
            });
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'shopee_model_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('shopee_model_id')->nullable()->after('shopee_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'shopee_model_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('shopee_model_id');
            });
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'shopee_item_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('shopee_item_id');
            });
        }

        if (Schema::hasTable('shopee_syncs')) {
            Schema::dropIfExists('shopee_syncs');
        }
    }
};
