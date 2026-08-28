<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopee_ads_settings') && ! Schema::hasColumn('shopee_ads_settings', 'item_auto_topup_enabled')) {
            Schema::table('shopee_ads_settings', function (Blueprint $table) {
                $table->boolean('item_auto_topup_enabled')->default(true)->after('item_replenish_enabled');
            });
        }

        if (! Schema::hasTable('shopee_ads_item_performance_snapshots')) {
            Schema::create('shopee_ads_item_performance_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('item_id');
                $table->string('campaign_id', 64)->nullable();
                $table->date('snapshot_date');
                $table->decimal('roas', 10, 4)->default(0);
                $table->unsignedInteger('spend')->default(0);
                $table->unsignedInteger('budget')->default(0);
                $table->timestamps();

                // MySQL identifier limit is 64 chars — always pass a short explicit name.
                $table->unique(['item_id', 'snapshot_date'], 'shopee_ads_item_perf_item_date_uq');
                $table->index(['snapshot_date', 'roas']);
                $table->index('item_id');
            });

            return;
        }

        Schema::table('shopee_ads_item_performance_snapshots', function (Blueprint $table) {
            if (! Schema::hasIndex('shopee_ads_item_performance_snapshots', 'shopee_ads_item_perf_item_date_uq')) {
                $table->unique(['item_id', 'snapshot_date'], 'shopee_ads_item_perf_item_date_uq');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('shopee_ads_settings') && Schema::hasColumn('shopee_ads_settings', 'item_auto_topup_enabled')) {
            Schema::table('shopee_ads_settings', function (Blueprint $table) {
                $table->dropColumn('item_auto_topup_enabled');
            });
        }

        if (Schema::hasTable('shopee_ads_item_performance_snapshots')
            && Schema::hasIndex('shopee_ads_item_performance_snapshots', 'shopee_ads_item_perf_item_date_uq')) {
            Schema::table('shopee_ads_item_performance_snapshots', function (Blueprint $table) {
                $table->dropUnique('shopee_ads_item_perf_item_date_uq');
            });
        }

        Schema::dropIfExists('shopee_ads_item_performance_snapshots');
    }
};
