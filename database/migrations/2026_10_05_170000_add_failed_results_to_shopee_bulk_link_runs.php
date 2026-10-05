<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_bulk_link_runs')) {
            return;
        }

        if (Schema::hasColumn('shopee_bulk_link_runs', 'failed_results')) {
            return;
        }

        Schema::table('shopee_bulk_link_runs', function (Blueprint $table) {
            $table->longText('failed_results')->nullable()->after('recent_results');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_bulk_link_runs')) {
            return;
        }

        if (! Schema::hasColumn('shopee_bulk_link_runs', 'failed_results')) {
            return;
        }

        Schema::table('shopee_bulk_link_runs', function (Blueprint $table) {
            $table->dropColumn('failed_results');
        });
    }
};
