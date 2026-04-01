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
        Schema::create('gajis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();
            $table->integer('bulan');
            $table->integer('tahun');
            $table->integer('bulanan')->default(0);
            $table->integer('harian')->default(0);
            $table->integer('premi')->default(0);
            $table->integer('cuti_sakit')->default(0);
            $table->integer('cuti_tahunan')->default(0);
            $table->integer('cuti_mendadak')->default(0);
            $table->integer('total_cuti')->default(0);
            $table->integer('potongan_cuti_bulanan')->default(0);
            $table->integer('potongan_cuti_premi')->default(0);
            $table->integer('total_potongan')->default(0);
            $table->integer('bonus')->default(0);
            $table->integer('sanksi')->default(0);
            $table->integer('total_gaji')->default(0);
            $table->integer('flag')->default(1);
            $table->foreignId('bank_id')->nullable()->constrained('addrbooks')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gajis');
    }
};
