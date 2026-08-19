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
        if (! Schema::hasTable('restock_histories')) {
        Schema::create('restock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->string('step');
            $table->string('action');
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->integer('qty_changed');
            $table->string('invoice')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_histories');
    }
};

