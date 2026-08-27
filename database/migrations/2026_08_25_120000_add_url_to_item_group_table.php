<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_group')) {
            return;
        }

        Schema::table('item_group', function (Blueprint $table) {
            if (! Schema::hasColumn('item_group', 'url')) {
                $table->string('url')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_group') || ! Schema::hasColumn('item_group', 'url')) {
            return;
        }

        Schema::table('item_group', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
