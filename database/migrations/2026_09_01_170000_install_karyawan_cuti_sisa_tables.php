<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('karyawan_cuti_sisa')) {
            Schema::create('karyawan_cuti_sisa', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('karyawan_id');
                $table->unsignedSmallInteger('tahun');
                $table->integer('sisa_tahunan')->default(12);
                $table->integer('sisa_sakit')->default(30);
                $table->timestamps();

                $table->unique(['karyawan_id', 'tahun'], 'kar_cuti_sisa_uq');
            });
        }

        if (! Schema::hasTable('karyawan_cuti_sisa_logs')) {
            Schema::create('karyawan_cuti_sisa_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('karyawan_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedSmallInteger('tahun');
                $table->string('sumber', 16)->default('manual');
                $table->integer('sisa_tahunan_lama')->default(0);
                $table->integer('sisa_tahunan_baru')->default(0);
                $table->integer('sisa_sakit_lama')->default(0);
                $table->integer('sisa_sakit_baru')->default(0);
                $table->string('catatan')->nullable();
                $table->timestamps();

                $table->index(['karyawan_id', 'tahun'], 'kar_cuti_sisa_log_idx');
                $table->index('user_id', 'kar_cuti_sisa_log_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_cuti_sisa_logs');
        Schema::dropIfExists('karyawan_cuti_sisa');
    }
};
