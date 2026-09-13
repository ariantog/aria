<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_insight_months')) {
            return;
        }

        Schema::table('item_insight_months', function (Blueprint $table) {
            if (! Schema::hasColumn('item_insight_months', 'months_included')) {
                $table->json('months_included')->nullable()->after('row_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_insight_months')) {
            return;
        }

        Schema::table('item_insight_months', function (Blueprint $table) {
            if (Schema::hasColumn('item_insight_months', 'months_included')) {
                $table->dropColumn('months_included');
            }
        });
    }
};
