<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_item_monthly_stats')) {
            return;
        }

        Schema::create('warehouse_item_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('sold_qty', 15, 2)->default(0);
            $table->decimal('returned_qty', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id', 'month', 'year'], 'wh_item_monthly_unique');
            $table->index(['warehouse_id', 'year', 'month'], 'wh_item_monthly_wh_period');
            $table->index(['item_id', 'year', 'month'], 'wh_item_monthly_item_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_item_monthly_stats');
    }
};
