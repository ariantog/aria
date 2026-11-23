<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return;
        }

        if (! Schema::hasColumn('shopee_syncs', 'direct_order_ingest')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->boolean('direct_order_ingest')->default(false)->after('shop_id');
            });
        }

        if (! Schema::hasColumn('shopee_syncs', 'last_order_poll_at')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->timestamp('last_order_poll_at')->nullable()->after('direct_order_ingest');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return;
        }

        if (Schema::hasColumn('shopee_syncs', 'last_order_poll_at')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->dropColumn('last_order_poll_at');
            });
        }

        if (Schema::hasColumn('shopee_syncs', 'direct_order_ingest')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->dropColumn('direct_order_ingest');
            });
        }
    }
};