<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_parent_prices') && ! Schema::hasTable('item_parents')) {
            Schema::rename('item_parent_prices', 'item_parents');
        }

        if (! Schema::hasTable('item_parents') && ! Schema::hasTable('item_parent_prices')) {
            Schema::create('item_parents', function (Blueprint $table) {
                $table->increments('id');
                $table->string('parent_key', 120)->nullable();
                $table->string('product_name', 255)->nullable();
                $table->text('description');
                $table->text('description2');
                $table->decimal('price', 20, 2)->default(0);
                $table->decimal('reseller_price', 20, 2)->default(0);
                $table->decimal('cost', 20, 2)->default(0);
                $table->decimal('cost_cnh', 20, 2)->default(0);
            });
        }

        if (Schema::hasTable('item_group') && ! Schema::hasColumn('item_group', 'parent_id')) {
            Schema::table('item_group', function (Blueprint $table) {
                $table->unsignedInteger('parent_id')->default(0)->after('id');
                $table->index('parent_id', 'item_group_parent_id_idx');
            });
        }
    }

    public function down(): void
    {
        // Production: maintainer manages column/table changes manually.
    }
};
