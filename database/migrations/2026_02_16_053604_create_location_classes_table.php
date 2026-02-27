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
        Schema::create('location_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->integer('type')->nullable(); // Customer type at the time of record
            $table->date('date');

            // Financial stats for the period
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->decimal('sell', 15, 2)->default(0);
            $table->decimal('buy', 15, 2)->default(0);
            $table->decimal('return', 15, 2)->default(0);
            $table->decimal('return_supplier', 15, 2)->default(0);
            $table->decimal('use', 15, 2)->default(0);
            $table->decimal('move', 15, 2)->default(0);
            $table->decimal('transfer', 15, 2)->default(0);
            $table->decimal('adjust', 15, 2)->default(0);
            $table->decimal('depreciation', 15, 2)->default(0);

            $table->timestamps();

            // Index for faster queries
            $table->index(['location_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_classes');
    }
};
