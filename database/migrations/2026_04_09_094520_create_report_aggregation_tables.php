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
        // 1. Nett Cash Table
        Schema::create('monthly_account_summaries', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->foreignId('addrbook_id')->constrained('addrbooks')->onDelete('cascade');
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->decimal('sell', 15, 2)->default(0);
            $table->decimal('return', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'addrbook_id'], 'account_summary_unique');
            $table->index(['year', 'month']);
        });

        // 2. Cash Flow Table
        Schema::create('monthly_category_summaries', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->tinyInteger('addrbook_type'); // Category ID
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->decimal('sell', 15, 2)->default(0);
            $table->decimal('buy', 15, 2)->default(0);
            $table->decimal('return', 15, 2)->default(0);
            $table->decimal('return_supplier', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'addrbook_type'], 'category_summary_unique');
            $table->index(['year', 'month']);
        });

        // 3. Stock Analysis Table
        Schema::create('daily_inventory_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained('addrbooks')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');

            $table->decimal('qty_sell', 15, 2)->default(0);
            $table->decimal('qty_buy', 15, 2)->default(0);
            $table->decimal('qty_move_in', 15, 2)->default(0);
            $table->decimal('qty_move_out', 15, 2)->default(0);
            $table->decimal('qty_return_in', 15, 2)->default(0);
            $table->decimal('qty_return_out', 15, 2)->default(0);
            $table->decimal('qty_adjust_in', 15, 2)->default(0);
            $table->decimal('qty_adjust_out', 15, 2)->default(0);

            $table->decimal('stock_on_hand', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['date', 'warehouse_id', 'item_id'], 'inventory_summary_unique');
            $table->index('date');
            $table->index(['warehouse_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_inventory_summaries');
        Schema::dropIfExists('monthly_category_summaries');
        Schema::dropIfExists('monthly_account_summaries');
    }
};
