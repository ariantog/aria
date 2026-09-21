<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deferred: addrbook (`customers`) is created in a later migration, and production
     * `warehouse_item` rows use INT ids without MySQL FK constraints. See
     * `2026_02_16_070218_install_greenfield_warehouse_item_table.php`.
     */
    public function up(): void
    {
        // Intentionally empty — historical filename kept for migration order parity.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_item');
    }
};
