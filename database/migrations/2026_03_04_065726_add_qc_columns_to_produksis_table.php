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
            if (! Schema::hasColumn('prod_produksi', 'qc_id')) {
                $table->foreignId('qc_id')->nullable()->after('jahit_date');
            }
            if (! Schema::hasColumn('prod_produksi', 'qc_date')) {
                $table->date('qc_date')->nullable()->after('qc_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_produksi', function (Blueprint $table) {
            $table->dropColumn(['qc_id', 'qc_date']);
        });
    }
};
