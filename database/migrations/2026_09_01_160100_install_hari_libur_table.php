<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hari_libur')) {
            return;
        }

        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nama');
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique('tanggal', 'hari_libur_tanggal_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};
