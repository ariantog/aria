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
            if (! Schema::hasColumn('karyawans', 'waktu_dibatasi')) {
                $table->boolean('waktu_dibatasi')->default(true)->after('premi');
            }
            if (! Schema::hasColumn('karyawans', 'jam_masuk')) {
                $table->string('jam_masuk', 5)->default('08:00')->after('waktu_dibatasi');
            }
            if (! Schema::hasColumn('karyawans', 'grace_period_menit')) {
                $table->unsignedSmallInteger('grace_period_menit')->nullable()->after('jam_masuk');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('karyawans')) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) {
            foreach (['grace_period_menit', 'jam_masuk', 'waktu_dibatasi'] as $column) {
                if (Schema::hasColumn('karyawans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
