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
        Schema::create('jubelioorders', function (Blueprint $table) {
            $table->id();
            $table->string('jubelio_order_id')->unique();
            $table->integer('source')->default(1);
            $table->string('invoice');
            $table->string('type');
            $table->string('order_status');
            $table->unsignedInteger('warehouse_id')->default(0);
            $table->unsignedInteger('jubelio_store_id')->default(0);
            $table->unsignedInteger('jubelio_location_id')->default(0);
            $table->integer('run_count')->default(0);
            $table->integer('error_type')->nullable();
            $table->longText('error')->nullable();
            $table->json('stock_error_items')->nullable();
            $table->longText('payload')->nullable();
            $table->integer('execute_by')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jubelioorders');
    }
};
