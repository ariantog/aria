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
        Schema::table('warehouse_items', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['item_id', 'warehouse_id']);

            // Add the new polymorphic unique constraint
            $table->unique(['item_id', 'warehouse_id', 'warehouse_type'], 'warehouse_items_item_warehouse_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropUnique('warehouse_items_item_warehouse_unique');
            $table->unique(['item_id', 'warehouse_id']);
        });
    }
};
