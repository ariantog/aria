<?php

use App\Support\LedgerDuplicateMergePlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge duplicate expense ledgers (gedung / kendaraan / kantor / toko).
 *
 * Production-safe data migration:
 * - No DROP / reshape of customers
 * - Soft-delete retired ledgers only
 * - Writes ledger_merge_maps (INT ids, no FK to partitioned transactions)
 * - Does not rewrite transactions or running balances
 *
 *   php artisan migrate --path=database/migrations/2026_08_29_150000_merge_duplicate_ledgers.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('ledger_merge_maps')) {
            return;
        }

        DB::transaction(fn () => LedgerDuplicateMergePlan::apply());
    }

    public function down(): void
    {
        // Production: do not un-merge or restore soft-deleted ledgers.
    }
};
