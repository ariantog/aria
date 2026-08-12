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
        Schema::create('stock_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_stock_report')->constrained('stok_reports')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_name');
            $table->decimal('score', 8, 4);
            $table->string('performance_key');
            $table->string('performance_level');
            $table->integer('gap_days')->nullable();
            $table->foreignId('current_warehouse_id')->constrained('customers');
            $table->string('current_warehouse_name');
            $table->integer('current_warehouse_qty');
            $table->string('current_warehouse_last_sale')->nullable();
            $table->integer('current_warehouse_days_ago')->nullable();
            $table->foreignId('best_performing_warehouse_id')->nullable()->constrained('customers');
            $table->string('best_performing_warehouse_name')->nullable();
            $table->string('best_performing_warehouse_last_sale')->nullable();
            $table->integer('best_performing_warehouse_days_ago')->nullable();
            $table->integer('best_performing_warehouse_qty')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_data');
    }
};
