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
            if (! Schema::hasColumn('shopee_ads_settings', 'double_date_enabled')) {
                $table->boolean('double_date_enabled')->default(false)->after('last_item_replenish_at');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'double_date_gmv_multiplier')) {
                $table->decimal('double_date_gmv_multiplier', 5, 2)->default(2)->after('double_date_enabled');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'double_date_item_ads_multiplier')) {
                $table->decimal('double_date_item_ads_multiplier', 5, 2)->default(1.5)->after('double_date_gmv_multiplier');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'double_date_item_budget_multiplier')) {
                $table->decimal('double_date_item_budget_multiplier', 5, 2)->default(2)->after('double_date_item_ads_multiplier');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'payday_enabled')) {
                $table->boolean('payday_enabled')->default(false)->after('double_date_item_budget_multiplier');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'payday_day')) {
                $table->unsignedTinyInteger('payday_day')->default(25)->after('payday_enabled');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'payday_gmv_multiplier')) {
                $table->decimal('payday_gmv_multiplier', 5, 2)->default(1.5)->after('payday_day');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'payday_item_multiplier')) {
                $table->decimal('payday_item_multiplier', 5, 2)->default(1.3)->after('payday_gmv_multiplier');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'manual_boost_multiplier')) {
                $table->decimal('manual_boost_multiplier', 5, 2)->default(1.5)->after('payday_item_multiplier');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_ads_settings')) {
            return;
        }

        Schema::table('shopee_ads_settings', function (Blueprint $table) {
            $columns = [
                'double_date_enabled',
                'double_date_gmv_multiplier',
                'double_date_item_ads_multiplier',
                'double_date_item_budget_multiplier',
                'payday_enabled',
                'payday_day',
                'payday_gmv_multiplier',
                'payday_item_multiplier',
                'manual_boost_multiplier',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('shopee_ads_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
