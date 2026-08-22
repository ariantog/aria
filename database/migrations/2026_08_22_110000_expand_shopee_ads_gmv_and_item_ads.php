<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopee_ads_settings')) {
            Schema::table('shopee_ads_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('shopee_ads_settings', 'starting_budget_gmv_max')) {
                    $table->unsignedInteger('starting_budget_gmv_max')->default(100000)->after('starting_budget');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'gms_campaign_id')) {
                    $table->string('gms_campaign_id', 64)->nullable()->after('produk_auto_campaign_id');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'gms_current_budget')) {
                    $table->unsignedInteger('gms_current_budget')->default(0)->after('gms_campaign_id');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_ads_enabled')) {
                    $table->boolean('item_ads_enabled')->default(true)->after('group_replenish_minute');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'max_item_ads')) {
                    $table->unsignedSmallInteger('max_item_ads')->default(10)->after('item_ads_enabled');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_ad_starting_budget')) {
                    $table->unsignedInteger('item_ad_starting_budget')->default(25000)->after('max_item_ads');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_replenish_enabled')) {
                    $table->boolean('item_replenish_enabled')->default(true)->after('item_ad_starting_budget');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_replenish_max_per_run')) {
                    $table->unsignedTinyInteger('item_replenish_max_per_run')->default(5)->after('item_replenish_enabled');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_roas_off_threshold')) {
                    $table->decimal('item_roas_off_threshold', 8, 2)->default(6)->after('item_replenish_max_per_run');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_off_after_checks')) {
                    $table->unsignedTinyInteger('item_off_after_checks')->default(2)->after('item_roas_off_threshold');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_new_roas_target')) {
                    $table->decimal('item_new_roas_target', 8, 2)->default(0)->after('item_off_after_checks');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_split_high')) {
                    $table->unsignedTinyInteger('item_split_high')->default(60)->after('item_new_roas_target');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_split_mid')) {
                    $table->unsignedTinyInteger('item_split_mid')->default(30)->after('item_split_high');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_split_low')) {
                    $table->unsignedTinyInteger('item_split_low')->default(10)->after('item_split_mid');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_replenish_hour')) {
                    $table->unsignedTinyInteger('item_replenish_hour')->default(2)->after('item_split_low');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'item_replenish_minute')) {
                    $table->unsignedTinyInteger('item_replenish_minute')->default(30)->after('item_replenish_hour');
                }
                if (! Schema::hasColumn('shopee_ads_settings', 'last_item_replenish_at')) {
                    $table->timestamp('last_item_replenish_at')->nullable()->after('last_replenish_at');
                }
            });
        }

        if (! Schema::hasTable('shopee_ads_item_ads')) {
            Schema::create('shopee_ads_item_ads', function (Blueprint $table) {
                $table->string('campaign_id', 64)->primary();
                $table->unsignedInteger('item_id');
                $table->string('origin', 16)->default('bot');
                $table->unsignedInteger('budget')->default(0);
                $table->decimal('roas_target', 8, 2)->default(0);
                $table->string('status', 32)->default('ongoing');
                $table->unsignedSmallInteger('increments_today')->default(0);
                $table->unsignedSmallInteger('low_roas_streak')->default(0);
                $table->decimal('last_roas', 10, 4)->nullable();
                $table->boolean('turned_off')->default(false);
                $table->timestamps();

                $table->index('item_id');
                $table->index(['turned_off', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_ads_item_ads');
    }
};
