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
            if (! Schema::hasColumn('shopee_ads_settings', 'gms_current_spend')) {
                $table->unsignedInteger('gms_current_spend')->default(0)->after('gms_current_budget');
            }
            if (! Schema::hasColumn('shopee_ads_settings', 'gms_current_spend_at')) {
                $table->timestamp('gms_current_spend_at')->nullable()->after('gms_current_spend');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_ads_settings')) {
            return;
        }

        Schema::table('shopee_ads_settings', function (Blueprint $table) {
            if (Schema::hasColumn('shopee_ads_settings', 'gms_current_spend_at')) {
                $table->dropColumn('gms_current_spend_at');
            }
            if (Schema::hasColumn('shopee_ads_settings', 'gms_current_spend')) {
                $table->dropColumn('gms_current_spend');
            }
        });
    }
};
