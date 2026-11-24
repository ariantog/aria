<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'tiktok_shop_product_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('tiktok_shop_product_id', 64)->nullable()->after('shopee_model_id');
            });
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'tiktok_shop_sku_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('tiktok_shop_sku_id', 64)->nullable()->after('tiktok_shop_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'tiktok_shop_sku_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('tiktok_shop_sku_id');
            });
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'tiktok_shop_product_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('tiktok_shop_product_id');
            });
        }
    }
};