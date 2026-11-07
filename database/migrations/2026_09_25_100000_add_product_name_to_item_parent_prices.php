<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = Schema::hasTable('item_parents') ? 'item_parents' : (Schema::hasTable('item_parent_prices') ? 'item_parent_prices' : null);

        if ($table === null) {
            return;
        }

        if (! Schema::hasColumn($table, 'product_name')) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('product_name', 255)->default('')->after('parent_key');
            });
        }
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
