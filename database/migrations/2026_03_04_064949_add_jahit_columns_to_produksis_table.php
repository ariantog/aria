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
            if (! Schema::hasColumn('prod_produksi', 'jahit_id')) {
                $table->foreignId('jahit_id')->nullable()->after('original_id');
            }
            if (! Schema::hasColumn('prod_produksi', 'jahit_date')) {
                $table->date('jahit_date')->nullable()->after('jahit_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_produksi', function (Blueprint $table) {
            $table->dropColumn(['jahit_id', 'jahit_date']);
        });
    }
};
