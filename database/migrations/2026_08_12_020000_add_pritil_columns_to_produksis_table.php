<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prod_produksi')) {
            return;
        }

        Schema::table('prod_produksi', function (Blueprint $table) {
            if (! Schema::hasColumn('prod_produksi', 'pritil_id')) {
                $table->foreignId('pritil_id')->nullable()->after('qc_date');
            }
            if (! Schema::hasColumn('prod_produksi', 'pritil_date')) {
                $table->dateTime('pritil_date')->nullable()->after('pritil_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prod_produksi', function (Blueprint $table) {
            $table->dropColumn(['pritil_id', 'pritil_date']);
        });
    }
};
