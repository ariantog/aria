<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aria-local mirror of marketplace "on order" reservations (does not change physical qty).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_item')) {
            return;
        }

        Schema::table('warehouse_item', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_item', 'on_order')) {
                $table->decimal('on_order', 15, 2)->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouse_item')) {
            return;
        }

        Schema::table('warehouse_item', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_item', 'on_order')) {
                $table->dropColumn('on_order');
            }
        });
    }
};