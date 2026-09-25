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

        if (! Schema::hasColumn('item_parent_prices', 'product_name')) {
            Schema::table('item_parent_prices', function (Blueprint $table) {
                $table->string('product_name', 255)->default('')->after('parent_key');
            });
        }
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
