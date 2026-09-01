<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('karyawans')) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawans', 'absen_id')) {
                $table->string('absen_id', 64)->nullable()->after('nama_absensi');
            }
            if (! Schema::hasColumn('karyawans', 'jam_kerja')) {
                $table->unsignedTinyInteger('jam_kerja')->default(8)->after('jam_masuk');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('karyawans')) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) {
            foreach (['jam_kerja', 'absen_id'] as $column) {
                if (Schema::hasColumn('karyawans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
