<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customerstat')) {
        Schema::create('customerstat', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->primary();
            $table->decimal('balance', 20, 2)->default(0);
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customerstat');
    }
};

