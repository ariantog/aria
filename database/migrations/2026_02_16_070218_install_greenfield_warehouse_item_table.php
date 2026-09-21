<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Greenfield `warehouse_item` — runs after `customers` and `items` exist.
 *
 * Production L10 MySQL has no FK on this table (INT ids). New-subdomain full
 * `migrate` must not add BIGINT FKs before addrbook exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_item')) {
            return;
        }

        if (! Schema::hasTable('customers') || ! Schema::hasTable('items')) {
            return;
        }

        Schema::create('warehouse_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('warehouse_id');
            $table->string('warehouse_type')->default('2'); // Addrbook::TYPE_WAREHOUSE
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamps();

            $table->index('item_id');
            $table->index(['warehouse_id', 'warehouse_type']);
            $table->unique(
                ['item_id', 'warehouse_id', 'warehouse_type'],
                'warehouse_item_item_warehouse_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_item');
    }
};
