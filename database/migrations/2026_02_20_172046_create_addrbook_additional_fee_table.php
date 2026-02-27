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
        Schema::create('addrbook_additional_fee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addrbook_id')->constrained('addrbooks')->onDelete('cascade');
            $table->foreignId('additional_fee_id')->constrained('additional_fees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addrbook_additional_fee');
    }
};
