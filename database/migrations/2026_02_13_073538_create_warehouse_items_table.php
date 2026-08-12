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
        Schema::create('warehouse_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('customers')->cascadeOnDelete();
            $table->string('warehouse_type')->default('2'); // Default to Addrbook::TYPE_WAREHOUSE
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['warehouse_id', 'warehouse_type']);
            $table->unique(['item_id', 'warehouse_id', 'warehouse_type'], 'warehouse_item_item_warehouse_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_item');
    }
};
