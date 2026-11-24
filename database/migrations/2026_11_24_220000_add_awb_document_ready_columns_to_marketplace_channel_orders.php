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

        if (! Schema::hasColumn('marketplace_channel_orders', 'awb_document_requested_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('awb_document_requested_at')->nullable();
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'awb_document_ready_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('awb_document_ready_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'awb_document_ready_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('awb_document_ready_at');
            });
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'awb_document_requested_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('awb_document_requested_at');
            });
        }
    }
};