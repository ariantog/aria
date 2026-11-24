<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_by')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->unsignedInteger('awb_printed_by')->nullable();
                $table->index('awb_printed_by', 'mkt_ch_ord_awb_by_idx');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('awb_printed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'awb_printed_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('awb_printed_at');
            });
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'awb_printed_by')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropIndex('mkt_ch_ord_awb_by_idx');
                $table->dropColumn('awb_printed_by');
            });
        }
    }
};