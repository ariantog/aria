<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_group')) {
            Schema::table('item_group', function (Blueprint $table) {
                if (! Schema::hasColumn('item_group', 'price')) {
                    $table->decimal('price', 20, 2)->default(0);
                }
                if (! Schema::hasColumn('item_group', 'cost')) {
                    $table->decimal('cost', 20, 2)->default(0);
                }
                if (! Schema::hasColumn('item_group', 'cost_cnh')) {
                    $table->decimal('cost_cnh', 20, 2)->default(0);
                }
            });
        }

        if (! Schema::hasTable('item_parents') && ! Schema::hasTable('item_parent_prices')) {
            Schema::create('item_parents', function (Blueprint $table) {
                $table->increments('id');
                $table->string('parent_key', 120)->nullable();
                $table->string('product_name', 255)->default('');
                $table->string('description', 200)->default('');
                $table->string('description2', 250)->default('');
                $table->decimal('price', 20, 2)->default(0);
                $table->decimal('reseller_price', 20, 2)->default(0);
                $table->decimal('cost', 20, 2)->default(0);
                $table->decimal('cost_cnh', 20, 2)->default(0);
                $table->unique('parent_key', 'item_parents_key_uq');
            });
        }
    }

    public function down(): void
    {
        // Live production tables — do not drop columns or tables.
    }
};
