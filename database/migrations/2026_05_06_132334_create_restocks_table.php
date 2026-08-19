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
        if (! Schema::hasTable('restocks')) {
        Schema::create('restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('status')->default(1);
            $table->integer('restocked_quantity')->default(0);
            $table->integer('in_production_quantity')->default(0);
            $table->integer('shipped_quantity')->default(0);
            $table->integer('missing_quantity')->default(0);
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restocks');
    }
};

