<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'pph')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('pph', 15, 2)->nullable()->after('ppn_dpp');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'pph')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('pph');
        });
    }
};
