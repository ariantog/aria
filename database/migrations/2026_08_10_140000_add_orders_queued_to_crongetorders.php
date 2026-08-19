<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crongetorders') || Schema::hasColumn('crongetorders', 'orders_queued')) {
            return;
        }

        Schema::table('crongetorders', function (Blueprint $table) {
            $table->unsignedInteger('orders_queued')->default(0)->after('cek_transaction');
        });
    }

    public function down(): void
    {
        Schema::table('crongetorders', function (Blueprint $table) {
            $table->dropColumn('orders_queued');
        });
    }
};
