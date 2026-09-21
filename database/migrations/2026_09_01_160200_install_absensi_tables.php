<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('absensi_imports')) {
            Schema::create('absensi_imports', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('karyawan_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('unmatched_count')->default(0);
                $table->unsignedInteger('day_count')->default(0);
                $table->timestamps();

                $table->index(['period_start', 'period_end'], 'absensi_imp_period_idx');
            });
        }

        if (! Schema::hasTable('absensi_hari')) {
            Schema::create('absensi_hari', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('import_id')->default(0);
                $table->unsignedInteger('karyawan_id')->nullable();
                $table->string('absen_id', 64)->default('');
                $table->string('nama_mesin')->nullable();
                $table->date('tanggal');
                $table->string('masuk', 5)->nullable();
                $table->string('pulang', 5)->nullable();
                $table->decimal('jam', 6, 2)->default(0);
                $table->string('punches_raw')->nullable();
                $table->boolean('incomplete')->default(false);
                $table->timestamps();

                $table->index('import_id', 'absensi_hari_import_idx');
                $table->index(['karyawan_id', 'tanggal'], 'absensi_hari_kar_tgl_idx');
                $table->index(['tanggal', 'absen_id'], 'absensi_hari_tgl_absen_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_hari');
        Schema::dropIfExists('absensi_imports');
    }
};
