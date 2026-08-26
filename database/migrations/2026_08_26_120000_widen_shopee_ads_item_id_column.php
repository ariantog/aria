<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shopee platform item ids exceed INT UNSIGNED (e.g. 44xxxxxxxxxx).
 * This table stores Shopee item ids, not Aria items.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_ads_item_ads') || ! Schema::hasColumn('shopee_ads_item_ads', 'item_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `shopee_ads_item_ads` MODIFY `item_id` BIGINT UNSIGNED NOT NULL');

            return;
        }

        Schema::table('shopee_ads_item_ads', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_ads_item_ads') || ! Schema::hasColumn('shopee_ads_item_ads', 'item_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `shopee_ads_item_ads` MODIFY `item_id` INT UNSIGNED NOT NULL');

            return;
        }

        Schema::table('shopee_ads_item_ads', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->change();
        });
    }
};
