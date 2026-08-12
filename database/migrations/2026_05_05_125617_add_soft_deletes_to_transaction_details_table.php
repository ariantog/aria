<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Retired: transactions are hard-deleted (archived to deleted_transactions tables), not soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op — kept so existing migration history stays valid.
    }

    public function down(): void
    {
        // No-op
    }
};
