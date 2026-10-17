<?php

use App\Support\RestockCellHistoryAuditSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent fix when 2026_10_17_100000 failed on MySQL errno 1832 (user_id FK).
 * Safe to run on production even if the audit migration already completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restock_cell_histories')) {
            return;
        }

        RestockCellHistoryAuditSchema::makeUserIdNullable();
        RestockCellHistoryAuditSchema::relaxRestockCellForeignKey();
    }

    public function down(): void
    {
        // Forward-only production fix.
    }
};
