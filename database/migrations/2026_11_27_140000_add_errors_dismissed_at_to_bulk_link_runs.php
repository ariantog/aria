<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopee_bulk_link_runs')
            && ! Schema::hasColumn('shopee_bulk_link_runs', 'errors_dismissed_at')) {
            Schema::table('shopee_bulk_link_runs', function (Blueprint $table) {
                $table->timestamp('errors_dismissed_at')->nullable()->after('failed_results');
            });
        }

        if (Schema::hasTable('tiktok_bulk_link_runs')
            && ! Schema::hasColumn('tiktok_bulk_link_runs', 'errors_dismissed_at')) {
            Schema::table('tiktok_bulk_link_runs', function (Blueprint $table) {
                $table->timestamp('errors_dismissed_at')->nullable()->after('failed_results');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shopee_bulk_link_runs')
            && Schema::hasColumn('shopee_bulk_link_runs', 'errors_dismissed_at')) {
            Schema::table('shopee_bulk_link_runs', function (Blueprint $table) {
                $table->dropColumn('errors_dismissed_at');
            });
        }

        if (Schema::hasTable('tiktok_bulk_link_runs')
            && Schema::hasColumn('tiktok_bulk_link_runs', 'errors_dismissed_at')) {
            Schema::table('tiktok_bulk_link_runs', function (Blueprint $table) {
                $table->dropColumn('errors_dismissed_at');
            });
        }
    }
};