<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('karyawan_gaji')) {
            return;
        }

        Schema::table('karyawan_gaji', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawan_gaji', 'jam_kerja_aktual')) {
                $table->decimal('jam_kerja_aktual', 8, 2)->default(0)->after('hari_izin');
            }
            if (! Schema::hasColumn('karyawan_gaji', 'jam_kerja_ekspektasi')) {
                $table->decimal('jam_kerja_ekspektasi', 8, 2)->default(0)->after('jam_kerja_aktual');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('karyawan_gaji')) {
            return;
        }

        Schema::table('karyawan_gaji', function (Blueprint $table) {
            foreach (['jam_kerja_ekspektasi', 'jam_kerja_aktual'] as $column) {
                if (Schema::hasColumn('karyawan_gaji', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
