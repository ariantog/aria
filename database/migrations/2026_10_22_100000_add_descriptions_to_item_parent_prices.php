<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_parent_prices')) {
            return;
        }

        Schema::table('item_parent_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('item_parent_prices', 'description')) {
                $table->string('description', 200)->default('')->after('product_name');
            }
            if (! Schema::hasColumn('item_parent_prices', 'description2')) {
                $table->string('description2', 250)->default('')->after('description');
            }
        });
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
