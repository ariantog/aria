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
            // Drop the old foreign key constraint
            $table->dropForeign(['warehouse_id']);

            // Add the new foreign key constraint to addrbooks
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('addrbooks')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();
        });
    }
};
