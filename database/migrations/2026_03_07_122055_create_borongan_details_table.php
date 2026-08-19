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
        if (! Schema::hasTable('prod_borongandetail')) {
        Schema::create('prod_borongandetail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borongan_id')->constrained('prod_borongan')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('produksi_id')->constrained('prod_produksi');
            $table->decimal('ongkos', 12, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_borongandetail');
    }
};

