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
        Schema::create('prod_produksi', function (Blueprint $table) {
            $table->id();
            $table->string('temp_name')->nullable();
            $table->foreignId('size_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('customer')->nullable();
            $table->string('warna')->nullable();
            $table->foreignId('potong_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('potong_date')->nullable();
            $table->string('surat_jalan_potong')->nullable();
            $table->integer('status')->default(1)->comment('1: Produksi, 2: Setor');
            $table->foreignId('original_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_produksi');
    }
};
