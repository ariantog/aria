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

        if (! Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->unsignedInteger('posted_transaction_id')->default(0)->after('awb_document_ready_at');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'posted_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('posted_at')->nullable()->after('posted_transaction_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'posted_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('posted_at');
            });
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('posted_transaction_id');
            });
        }
    }
};