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
        if (! Schema::hasTable('prod_produksi')) {
            return;
        }

        Schema::table('prod_produksi', function (Blueprint $table) {
            if (! Schema::hasColumn('prod_produksi', 'item_id')) {
                $table->foreignId('item_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('prod_produksi', 'invoice')) {
                $table->string('invoice')->nullable()->after('status');
            }
            if (! Schema::hasColumn('prod_produksi', 'transaction_id')) {
                $table->foreignId('transaction_id')->nullable()->after('invoice');
            }
            if (! Schema::hasColumn('prod_produksi', 'detail_id')) {
                $table->foreignId('detail_id')->nullable()->after('transaction_id');
            }
            if (! Schema::hasColumn('prod_produksi', 'setor_date')) {
                $table->date('setor_date')->nullable()->after('detail_id');
            }
            if (! Schema::hasColumn('prod_produksi', 'gudang_date')) {
                $table->date('gudang_date')->nullable()->after('setor_date');
            }
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
