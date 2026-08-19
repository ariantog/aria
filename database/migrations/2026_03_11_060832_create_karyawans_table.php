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
        if (! Schema::hasTable('karyawans')) {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->longText('alamat');
            $table->string('no_telp');
            $table->integer('bulanan')->nullable();
            $table->integer('harian')->nullable();
            $table->integer('premi')->nullable();
            $table->integer('flag')->default(1);
            $table->foreignId('bank_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};

