<?php

namespace App\Support;

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate expense-ledger merges (gedung / kendaraan / kantor / toko).
 *
 * Does not rewrite transactions.sender_id / receiver_id or running balances.
 * Historical CashOut stays on the old id; reports follow ledger_merge_maps.
 */
final class LedgerDuplicateMergePlan
{
    /**
     * @return array<int, string> ledger id => canonical name
     */
    public static function renames(): array
    {
        return [
            860 => 'Biaya Perbaikan Gedung',
            2099 => 'Biaya Metro',
            2178 => 'Biaya Sogo',
            2273 => 'Biaya Tokopedia',
            2633 => 'Biaya Central',
            2842 => 'Biaya Toko Citos',
            2889 => 'Biaya Toko WTC',
            2959 => 'Biaya Sewa Gedung',
        ];
    }

    /**
     * @return array<int, int> retired ledger id => canonical ledger id
     */
    public static function merges(): array
    {
        return [
            1180 => 860,  // Biaya Perbaikan Gedung (Lain-lain) → Perawatan
            2184 => 2889, // WTC Transport → Toko WTC
            2225 => 847,  // Office Inventories → Perlengkapan Kantor
            2826 => 862,  // Biaya Kendaraan catch-all → Servis Kendaraan
            2844 => 2842, // Sewa Citos → Toko Citos
            2854 => 2842, // FX Cost → Toko Citos
        ];
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    public static function apply(bool $dry = false, ?callable $log = null): void
    {
        $log ??= static function (string $message): void {};

        if (! Schema::hasTable('customers') || ! Schema::hasTable('ledger_merge_maps')) {
            $log('Skip ledger merges: customers or ledger_merge_maps table missing.');

            return;
        }

        foreach (self::renames() as $id => $name) {
            $ledger = Addrbook::query()->find($id);
            if (! $ledger || $ledger->name === $name) {
                continue;
            }

            $log("Rename {$id}: {$ledger->name} → {$name}");
            if (! $dry) {
                $ledger->update(['name' => $name]);
            }
        }

        foreach (self::merges() as $oldId => $newId) {
            $old = Addrbook::withTrashed()->find($oldId);
            $keeper = Addrbook::query()->find($newId);
            if (! $old || ! $keeper) {
                continue;
            }

            $log("Merge map {$oldId} ({$old->name}) → {$newId} ({$keeper->name})");
            if ($dry) {
                continue;
            }

            LedgerMergeMap::updateOrCreate(
                ['old_customer_id' => $oldId],
                ['new_customer_id' => $newId],
            );

            if (! $old->trashed()) {
                $old->delete();
            }
        }
    }
}
