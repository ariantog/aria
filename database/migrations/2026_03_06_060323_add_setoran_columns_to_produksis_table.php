<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prod_produksi', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('id');
            $table->string('invoice')->nullable()->after('status');
            $table->foreignId('transaction_id')->nullable()->after('invoice');
            $table->foreignId('detail_id')->nullable()->after('transaction_id');
            $table->date('setor_date')->nullable()->after('detail_id');
            $table->date('gudang_date')->nullable()->after('setor_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_produksi', function (Blueprint $table) {
            $table->dropColumn([
                'item_id',
                'invoice',
                'transaction_id',
                'detail_id',
                'setor_date',
                'gudang_date',
            ]);
        });
    }
};
