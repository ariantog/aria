<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production retains `item_group.alias` (legacy); an earlier L12 migration dropped it for greenfield SQLite only.
     * Restore the column when missing so catalog/backfill tools can read marketing strings.
     */
    public function up(): void
    {
        if (! Schema::hasTable('item_group') || Schema::hasColumn('item_group', 'alias')) {
            return;
        }

        Schema::table('item_group', function (Blueprint $table) {
            $table->string('alias', 100)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('item_group') || ! Schema::hasColumn('item_group', 'alias')) {
            return;
        }

        Schema::table('item_group', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
