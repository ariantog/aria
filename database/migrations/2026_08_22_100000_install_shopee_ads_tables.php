<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_ads_settings')) {
            Schema::create('shopee_ads_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary()->default(1);
                $table->string('status', 16)->default('active');
                $table->unsignedInteger('starting_budget')->default(100000);
                $table->unsignedInteger('daily_max_budget')->default(500000);
                $table->unsignedTinyInteger('group_split_high')->default(60);
                $table->unsignedTinyInteger('group_split_mid')->default(30);
                $table->unsignedTinyInteger('group_split_low')->default(10);
                $table->decimal('group_roas_off_threshold', 8, 2)->default(6);
                $table->unsignedTinyInteger('group_off_after_increments')->default(2);
                $table->boolean('group_replenish_enabled')->default(true);
                $table->unsignedSmallInteger('group_target_active_count')->default(6);
                $table->unsignedTinyInteger('group_replenish_max_per_run')->default(3);
                $table->decimal('group_replenish_min_roas', 8, 2)->default(6);
                $table->decimal('group_roas_target', 8, 2)->default(0);
                $table->unsignedTinyInteger('daily_reset_hour')->default(0);
                $table->unsignedTinyInteger('daily_reset_minute')->default(1);
                $table->unsignedTinyInteger('group_replenish_hour')->default(2);
                $table->unsignedTinyInteger('group_replenish_minute')->default(0);
                $table->string('toko_auto_campaign_id', 64)->nullable();
                $table->string('toko_manual_campaign_id', 64)->nullable();
                $table->string('produk_auto_campaign_id', 64)->nullable();
                $table->timestamp('last_daily_reset_at')->nullable();
                $table->timestamp('last_replenish_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shopee_ads_schedules')) {
            Schema::create('shopee_ads_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('ad_type', 32);
                $table->string('run_time', 5);
                $table->unsignedInteger('increment_idr');
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->unique(['ad_type', 'run_time']);
            });
        }

        if (! Schema::hasTable('shopee_ads_group_states')) {
            Schema::create('shopee_ads_group_states', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 64);
                $table->unsignedSmallInteger('increments_today')->default(0);
                $table->unsignedSmallInteger('low_roas_streak')->default(0);
                $table->decimal('last_roas', 10, 4)->nullable();
                $table->boolean('turned_off')->default(false);
                $table->timestamps();

                $table->unique('campaign_id');
            });
        }

        if (! Schema::hasTable('shopee_ads_budget_history')) {
            Schema::create('shopee_ads_budget_history', function (Blueprint $table) {
                $table->id();
                $table->string('ad_type', 32)->nullable();
                $table->string('campaign_id', 64)->nullable();
                $table->string('action', 32);
                $table->unsignedInteger('before_budget')->nullable();
                $table->unsignedInteger('after_budget')->nullable();
                $table->unsignedInteger('increment_idr')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_ads_budget_history');
        Schema::dropIfExists('shopee_ads_group_states');
        Schema::dropIfExists('shopee_ads_schedules');
        Schema::dropIfExists('shopee_ads_settings');
    }
};
