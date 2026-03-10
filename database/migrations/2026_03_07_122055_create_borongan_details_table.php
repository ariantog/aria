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
        Schema::create('borongan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borongan_id')->constrained('borongans')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('produksi_id')->constrained('produksis');
            $table->decimal('ongkos', 12, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borongan_details');
    }
};
