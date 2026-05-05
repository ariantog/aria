<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Delete rows where warehouse_id = 0 (invalid data causing FK failures)
        DB::table('warehouse_items')->where('warehouse_id', 0)->delete();

        // 2. Fix invalid warehouse_type 'AppModelsLocation' to proper class name
        DB::table('warehouse_items')
            ->where('warehouse_type', 'AppModelsLocation')
            ->update(['warehouse_type' => 'App\\Models\\Addrbook']);

        // 3. Update the column default value for future consistency
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->string('warehouse_type')->default('App\\Models\\Addrbook')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->string('warehouse_type')->default('AppModelsLocation')->change();
        });
    }
};
