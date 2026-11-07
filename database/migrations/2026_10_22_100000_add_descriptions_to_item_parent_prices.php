<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('item_parents') ? 'item_parents' : (Schema::hasTable('item_parent_prices') ? 'item_parent_prices' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'description')) {
                $table->string('description', 200)->default('')->after('product_name');
            }
            if (! Schema::hasColumn($tableName, 'description2')) {
                $table->string('description2', 250)->default('')->after('description');
            }
        });
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
