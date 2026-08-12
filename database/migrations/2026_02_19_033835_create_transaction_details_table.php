<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Greenfield / SQLite test schema aligned with production transaction_details
     * (see database/new.sql). Audit columns (date, transaction_type, …) are added
     * in 2026_04_18_153310_add_columns_to_transaction_details_table.php.
     */
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('price', 20, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->decimal('transaction_disc', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
