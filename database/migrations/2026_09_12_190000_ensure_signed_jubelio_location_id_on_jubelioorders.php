<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-apply signed jubelio_location_id when the first fix migration no-op'd (e.g. DB_CONNECTION=mariadb).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders') || ! Schema::hasColumn('jubelioorders', 'jubelio_location_id')) {
            return;
        }

        if (! $this->usesMysqlFamily()) {
            Schema::table('jubelioorders', function (Blueprint $table) {
                $table->integer('jubelio_location_id')->default(0)->change();
            });

            return;
        }

        DB::statement(
            'ALTER TABLE `jubelioorders` MODIFY `jubelio_location_id` INT(11) NOT NULL DEFAULT 0'
        );
    }

    public function down(): void
    {
        // Rows may store Jubelio location_id -1 (Pusat); do not shrink to UNSIGNED on production.
    }

    private function usesMysqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
