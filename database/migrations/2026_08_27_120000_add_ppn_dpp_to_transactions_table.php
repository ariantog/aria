<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'ppn_dpp')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('ppn_dpp', 15, 2)->nullable()->after('ppn');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'ppn_dpp')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('ppn_dpp');
        });
    }
};
