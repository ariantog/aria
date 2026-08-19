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
        if (! Schema::hasTable('deleted_details')) {
        Schema::create('deleted_details', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('id')->primary();
            $blueprint->unsignedBigInteger('transaction_id')->index();
            $blueprint->date('date')->nullable();
            $blueprint->tinyInteger('transaction_type')->unsigned()->nullable();
            $blueprint->unsignedBigInteger('sender_id')->nullable();
            $blueprint->unsignedBigInteger('receiver_id')->nullable();
            $blueprint->unsignedBigInteger('item_id')->nullable();
            $blueprint->decimal('quantity', 15, 2)->nullable();
            $blueprint->decimal('price', 15, 2)->nullable();
            $blueprint->decimal('discount', 15, 2)->nullable();
            $blueprint->decimal('total', 15, 2)->nullable();
            $blueprint->text('notes')->nullable();
            $blueprint->timestamp('created_at')->nullable();
            $blueprint->timestamp('updated_at')->nullable();
            $blueprint->timestamp('deleted_at')->nullable();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_details');
    }
};

