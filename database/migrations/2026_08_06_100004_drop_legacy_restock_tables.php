<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('restock_histories');
        Schema::dropIfExists('restocks');
    }

    public function down(): void
    {
        Schema::create('restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->string('size_id')->nullable();
            $table->string('size_type')->nullable();
            $table->date('date');
            $table->integer('status')->default(1);
            $table->integer('restocked_quantity')->default(0);
            $table->integer('in_production_quantity')->default(0);
            $table->integer('shipped_quantity')->default(0);
            $table->integer('missing_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('restock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->string('size_id')->nullable();
            $table->string('step');
            $table->string('action');
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->integer('qty_changed');
            $table->string('invoice')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
        });
    }
};
