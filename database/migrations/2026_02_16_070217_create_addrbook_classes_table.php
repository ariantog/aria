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
        Schema::create('addrbook_dailies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addrbook_id');
            $table->string('type')->nullable(); // Transaction type
            $table->date('date');
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->decimal('sell', 15, 2)->default(0);
            $table->decimal('buy', 15, 2)->default(0);
            $table->decimal('return', 15, 2)->default(0);
            $table->decimal('return_supplier', 15, 2)->default(0);
            $table->decimal('use', 15, 2)->default(0);
            $table->decimal('move', 15, 2)->default(0);
            $table->decimal('transfer', 15, 2)->default(0);
            $table->decimal('adjust', 15, 2)->default(0);
            $table->decimal('depreciation', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('addrbook_id', 'addrbook_classes_addrbook_id_foreign')->references('id')->on('addrbooks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addrbook_dailies');
    }
};
