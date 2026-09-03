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
            if (! Schema::hasColumn('item_group', 'brand')) {
                $table->integer('brand')->default(0);
            }

            if (! Schema::hasColumn('item_group', 'genre')) {
                $table->integer('genre')->default(0);
            }
        });
    }

    public function down(): void
    {
        // item_group is a live production table — do not drop columns.
    }
};
