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
        Schema::create('jubelio_stock_checks', function (Blueprint $table) {
            $table->id();
            $table->integer('page_tracking')->default(1);
            $table->string('status')->default('created');
            $table->timestamps();
        });

        Schema::create('jubelio_stock_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jubelio_stock_check_id')->constrained('jubelio_stock_checks')->onDelete('cascade');
            $table->unsignedBigInteger('jubelio_item_id');
            $table->integer('jubelio_location_id');
            $table->integer('warehouse_id'); // Aria warehouse id
            $table->decimal('aria_qty', 15, 2);
            $table->decimal('jubelio_qty', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jubelio_stock_discrepancies');
        Schema::dropIfExists('jubelio_stock_checks');
    }
};
