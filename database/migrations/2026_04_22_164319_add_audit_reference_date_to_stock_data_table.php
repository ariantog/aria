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
        Schema::table('stock_data', function (Blueprint $table) {
            $table->string('audit_reference_date')->nullable()->after('best_performing_warehouse_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_data', function (Blueprint $table) {
            $table->dropColumn('audit_reference_date');
        });
    }
};
