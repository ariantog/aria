<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached Inventory Health rows for the report page.
 *
 *   php artisan migrate --path=database/migrations/2026_08_31_100000_install_inventory_health_snapshots_table.php --force
 *
 * New L12 table only (not in old.sql). Guarded CREATE. No DROP of production tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_health_snapshots')) {
            return;
        }

        Schema::create('inventory_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->integer('warehouse_id')->default(0);
            $table->integer('item_id');
            $table->decimal('sold_period', 15, 2)->default(0);
            $table->decimal('returned_period', 15, 2)->default(0);
            $table->decimal('sold_extended', 15, 2)->default(0);
            $table->decimal('returned_extended', 15, 2)->default(0);
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->date('last_sold_at')->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->date('extended_from');
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id'], 'inv_health_snap_wh_item_uq');
            $table->index(['item_id'], 'inv_health_snap_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_health_snapshots');
    }
};
