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
        if (! Schema::hasTable('jubelioreturns')) {
        Schema::create('jubelioreturns', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('method_pay')->nullable();
            $table->string('invoice')->nullable();
            $table->longText('pesan')->nullable();
            $table->string('location_name')->nullable();
            $table->string('store_name')->nullable();
            $table->integer('status')->default(0);
            $table->integer('confirmed_by');
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jubelioreturns');
    }
};

