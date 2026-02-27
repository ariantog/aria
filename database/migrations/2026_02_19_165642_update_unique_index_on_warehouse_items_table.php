<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            // Drop foreign keys first because they depend on the index we want to drop
            $table->dropForeign(['item_id']);
            $table->dropForeign(['warehouse_id']);
        });

        // Clean up orphaned records that might prevent re-adding foreign keys
        DB::table('warehouse_items')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('items')
                    ->whereRaw('items.id = warehouse_items.item_id');
            })
            ->delete();

        DB::table('warehouse_items')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('addrbooks')
                    ->whereRaw('addrbooks.id = warehouse_items.warehouse_id');
            })
            ->delete();

        Schema::table('warehouse_items', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['item_id', 'warehouse_id']);

            // Add the new polymorphic unique constraint
            $table->unique(['item_id', 'warehouse_id', 'warehouse_type'], 'warehouse_items_item_warehouse_unique');

            // Re-add foreign keys
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('addrbooks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['warehouse_id']);

            $table->dropUnique('warehouse_items_item_warehouse_unique');
            $table->unique(['item_id', 'warehouse_id']);

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('addrbooks')->onDelete('cascade');
        });
    }
};
