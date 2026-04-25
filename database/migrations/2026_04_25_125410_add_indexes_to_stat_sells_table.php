<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stat_sells', function (Blueprint $table) {
            $table->index('bulan');
            $table->index('tahun');
            $table->index('group_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stat_sells', function (Blueprint $table) {
            $table->dropIndex(['bulan']);
            $table->dropIndex(['tahun']);
            $table->dropIndex(['group_id']);
            $table->dropIndex(['type']);
        });
    }
};
