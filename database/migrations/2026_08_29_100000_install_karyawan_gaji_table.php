<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('karyawan_gaji')) {
            return;
        }

        Schema::create('karyawan_gaji', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('karyawan_id');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->integer('bulanan')->default(0);
            $table->integer('harian')->default(0);
            $table->integer('cuti_tahunan')->default(0);
            $table->integer('cuti_sakit')->default(0);
            $table->integer('cuti_mendadak')->default(0);
            $table->integer('hari_izin')->default(0);
            $table->integer('potongan_harian')->default(0);
            $table->integer('menit_telat')->default(0);
            $table->integer('potongan_telat')->default(0);
            $table->decimal('jam_lembur', 8, 2)->default(0);
            $table->integer('upah_lembur')->default(0);
            $table->integer('bonus')->default(0);
            $table->integer('sanksi')->default(0);
            $table->integer('total_potongan')->default(0);
            $table->integer('total_gaji')->default(0);
            $table->unsignedTinyInteger('flag')->default(1);
            $table->unsignedInteger('bank_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['karyawan_id', 'bulan', 'tahun'], 'karyawan_gaji_period_uq');
            $table->index(['tahun', 'bulan'], 'karyawan_gaji_year_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_gaji');
    }
};
