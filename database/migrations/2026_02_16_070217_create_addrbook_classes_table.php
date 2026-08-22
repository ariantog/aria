<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_class', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->integer('customer_type')->nullable();
            $table->date('date');
            $table->decimal('cash_in', 20, 2)->default(0);
            $table->decimal('cash_out', 20, 2)->default(0);
            $table->decimal('sell', 20, 2)->default(0);
            $table->decimal('buy', 20, 2)->default(0);
            $table->decimal('return', 20, 2)->default(0);
            $table->decimal('return_supplier', 20, 2)->default(0);
            $table->decimal('use', 20, 2)->default(0);
            $table->decimal('move', 20, 2)->default(0);
            $table->decimal('transfer', 20, 2)->default(0);
            $table->decimal('adjust', 20, 2)->default(0);
            $table->decimal('depreciation', 20, 2)->default(0);
            $table->string('class')->default('');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->unique(['customer_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_class');
    }
};
