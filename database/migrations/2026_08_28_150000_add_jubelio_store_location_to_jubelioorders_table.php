<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelioorders', 'jubelio_store_id')) {
                $table->unsignedInteger('jubelio_store_id')->default(0)->after('warehouse_id');
            }

            if (! Schema::hasColumn('jubelioorders', 'jubelio_location_id')) {
                $table->unsignedInteger('jubelio_location_id')->default(0)->after('jubelio_store_id');
                $table->index(
                    ['jubelio_store_id', 'jubelio_location_id'],
                    'jubelioorders_store_loc_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (Schema::hasColumn('jubelioorders', 'jubelio_location_id')) {
                $table->dropIndex('jubelioorders_store_loc_idx');
                $table->dropColumn('jubelio_location_id');
            }

            if (Schema::hasColumn('jubelioorders', 'jubelio_store_id')) {
                $table->dropColumn('jubelio_store_id');
            }
        });
    }
};
