<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_parents')) {
            return;
        }

        Schema::table('item_parents', function (Blueprint $table) {
            if (! Schema::hasColumn('item_parents', 'url')) {
                $table->string('url', 500)->default('')->after('description2');
            }
            if (! Schema::hasColumn('item_parents', 'brand')) {
                $table->unsignedInteger('brand')->default(0)->after('url');
            }
            if (! Schema::hasColumn('item_parents', 'genre')) {
                $table->unsignedInteger('genre')->default(0)->after('brand');
            }
        });
    }

    public function down(): void
    {
        // Production: maintainer manages column changes manually.
    }
};
