<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_ads_settings')) {
            return;
        }

        Schema::table('shopee_ads_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('shopee_ads_settings', 'item_replenish_min_roas')) {
                $table->decimal('item_replenish_min_roas', 8, 2)->default(6)->after('item_roas_off_threshold');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_ads_settings')
            || ! Schema::hasColumn('shopee_ads_settings', 'item_replenish_min_roas')) {
            return;
        }

        Schema::table('shopee_ads_settings', function (Blueprint $table) {
            $table->dropColumn('item_replenish_min_roas');
        });
    }
};
