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

        if (! Schema::hasColumn('shopee_syncs', 'invoice_prefix')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->string('invoice_prefix', 10)->default('SP')->after('shop_id');
            });
        }

    }

    public function down(): void
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return;
        }

        if (Schema::hasColumn('shopee_syncs', 'invoice_prefix')) {
            Schema::table('shopee_syncs', function (Blueprint $table) {
                $table->dropColumn('invoice_prefix');
            });
        }
    }
};