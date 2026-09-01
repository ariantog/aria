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
            if (! Schema::hasColumn('karyawans', 'nama_absensi')) {
                $table->string('nama_absensi')->nullable()->after('nama');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('karyawans') || ! Schema::hasColumn('karyawans', 'nama_absensi')) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn('nama_absensi');
        });
    }
};
