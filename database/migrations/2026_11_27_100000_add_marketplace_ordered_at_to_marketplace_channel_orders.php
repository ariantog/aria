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

        if (Schema::hasColumn('marketplace_channel_orders', 'marketplace_ordered_at')) {
            return;
        }

        Schema::table('marketplace_channel_orders', function (Blueprint $table) {
            $table->dateTime('marketplace_ordered_at')->nullable()->after('paid_at');
            $table->index('marketplace_ordered_at', 'mkt_ch_ord_ordered_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'marketplace_ordered_at')) {
            return;
        }

        Schema::table('marketplace_channel_orders', function (Blueprint $table) {
            $table->dropIndex('mkt_ch_ord_ordered_idx');
            $table->dropColumn('marketplace_ordered_at');
        });
    }
};