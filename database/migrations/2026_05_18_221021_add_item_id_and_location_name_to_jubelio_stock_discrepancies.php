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
        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->after('jubelio_item_id');
            $table->string('jubelio_location_name')->nullable()->after('jubelio_location_id');

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn(['item_id', 'jubelio_location_name']);
        });
    }
};
