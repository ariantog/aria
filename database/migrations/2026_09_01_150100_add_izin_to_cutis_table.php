<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cutis')) {
            return;
        }

        Schema::table('cutis', function (Blueprint $table) {
            if (! Schema::hasColumn('cutis', 'izin')) {
                $table->integer('izin')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cutis') || ! Schema::hasColumn('cutis', 'izin')) {
            return;
        }

        Schema::table('cutis', function (Blueprint $table) {
            $table->dropColumn('izin');
        });
    }
};
