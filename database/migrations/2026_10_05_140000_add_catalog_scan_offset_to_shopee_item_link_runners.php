<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_item_link_runners')) {
            return;
        }

        if (! Schema::hasColumn('shopee_item_link_runners', 'catalog_scan_offset')) {
            Schema::table('shopee_item_link_runners', function (Blueprint $table) {
                $table->unsignedInteger('catalog_scan_offset')->default(0)->after('calls_hour_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shopee_item_link_runners')
            && Schema::hasColumn('shopee_item_link_runners', 'catalog_scan_offset')) {
            Schema::table('shopee_item_link_runners', function (Blueprint $table) {
                $table->dropColumn('catalog_scan_offset');
            });
        }
    }
};
