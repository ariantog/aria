<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * jubeliosyncs.jubelio_location_id is signed int(11) (production uses -1 = "Pusat").
 * L12 added jubelioorders.jubelio_location_id as UNSIGNED — MySQL rejects persisting -1 on refresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders') || ! Schema::hasColumn('jubelioorders', 'jubelio_location_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `jubelioorders` MODIFY `jubelio_location_id` INT NOT NULL DEFAULT 0'
            );

            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            $table->integer('jubelio_location_id')->default(0)->change();
        });
    }

    public function down(): void
    {
        // Rows may store Jubelio location_id -1 (Pusat); do not shrink to UNSIGNED on production.
    }
};
