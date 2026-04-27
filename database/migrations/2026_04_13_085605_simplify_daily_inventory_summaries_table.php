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
        Schema::table('daily_inventory_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'qty_buy',
                'qty_move_in',
                'qty_move_out',
                'qty_return_in',
                'qty_return_out',
                'qty_adjust_in',
                'qty_adjust_out',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_inventory_summaries', function (Blueprint $table) {
            $table->decimal('qty_buy', 15, 2)->default(0);
            $table->decimal('qty_move_in', 15, 2)->default(0);
            $table->decimal('qty_move_out', 15, 2)->default(0);
            $table->decimal('qty_return_in', 15, 2)->default(0);
            $table->decimal('qty_return_out', 15, 2)->default(0);
            $table->decimal('qty_adjust_in', 15, 2)->default(0);
            $table->decimal('qty_adjust_out', 15, 2)->default(0);
        });
    }
};
