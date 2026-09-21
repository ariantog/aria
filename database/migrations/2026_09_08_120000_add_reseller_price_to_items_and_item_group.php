<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'reseller_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('reseller_price', 20, 2)->default(0);
            });
        }

        if (Schema::hasTable('item_group') && ! Schema::hasColumn('item_group', 'reseller_price')) {
            Schema::table('item_group', function (Blueprint $table) {
                $table->decimal('reseller_price', 20, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
