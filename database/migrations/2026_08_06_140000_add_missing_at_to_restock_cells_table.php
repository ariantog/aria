<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restock_cells', function (Blueprint $table) {
            $table->timestamp('missing_at')->nullable()->after('qty_missing');
        });
    }

    public function down(): void
    {
        Schema::table('restock_cells', function (Blueprint $table) {
            $table->dropColumn('missing_at');
        });
    }
};
