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
        $indexes = Schema::getIndexes('stat_sells');
        $indexNames = array_column($indexes, 'name');

        Schema::table('stat_sells', function (Blueprint $table) use ($indexNames) {
            if (! in_array('stat_sells_bulan_index', $indexNames)) {
                $table->index('bulan');
            }
            if (! in_array('stat_sells_tahun_index', $indexNames)) {
                $table->index('tahun');
            }
            if (! in_array('stat_sells_group_id_index', $indexNames)) {
                $table->index('group_id');
            }
            if (! in_array('stat_sells_type_index', $indexNames)) {
                $table->index('type');
            }
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
