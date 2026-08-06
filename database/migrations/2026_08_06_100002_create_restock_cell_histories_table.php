<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_cell_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_cell_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->integer('qty_before')->default(0);
            $table->integer('qty_after')->default(0);
            $table->string('action');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['restock_cell_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_cell_histories');
    }
};
