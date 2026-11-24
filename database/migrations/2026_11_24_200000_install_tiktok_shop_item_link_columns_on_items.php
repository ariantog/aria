<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Install path when 2026_11_24_120000_add_tiktok_shop_item_link_columns_to_items
 * is recorded in `migrations` but columns were never applied (wrong env, partial failure).
 * No after() — safe when shopee_model_id is missing or column order differs on legacy prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'tiktok_shop_product_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('tiktok_shop_product_id', 64)->nullable();
            });
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'tiktok_shop_sku_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('tiktok_shop_sku_id', 64)->nullable();
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