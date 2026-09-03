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
            814 => 'Gaji Outsourcing',
            816 => 'Gaji Lain',
            835 => 'Biaya Perjalanan Marketing',
            858 => 'Biaya Ongkir',
            860 => 'Biaya Perbaikan Gedung',
            880 => 'Penyesuaian Umum',
            905 => 'Bonus & Insentif',
            2099 => 'Biaya Metro',
            2149 => 'Biaya Fotografi',
            2178 => 'Biaya Sogo',
            2234 => 'Biaya Shopee',
            2250 => 'Biaya Marketing Digital',
            2273 => 'Biaya Tokopedia',
            2633 => 'Biaya Central',
            2719 => 'Biaya FitBox',
            2788 => 'Biaya TikTok',
            2799 => 'Perlengkapan Produksi',
            2842 => 'Biaya Toko Citos',
            2881 => 'Biaya Lazada',
            2889 => 'Biaya Toko WTC',
            2899 => 'Biaya BSD',
            2957 => 'Biaya MUKU',
            2959 => 'Biaya Sewa Gedung',
            2963 => 'Biaya AF',
            2964 => 'Biaya Prop',
        ];
    }

    /**
     * @return array<int, int> retired ledger id => canonical ledger id
     */
    public static function merges(): array
    {
        return [
            813 => 905,   // THR → Bonus & Insentif
            820 => 905,   // Insentif → Bonus & Insentif
            821 => 816,   // Gaji Pembantu → Gaji Lain
            825 => 814,   // Gaji Finishing → Gaji Outsourcing
            863 => 2799,  // Aksesoris Mesin → Perlengkapan Produksi
            899 => 814,   // Jahit Luar → Gaji Outsourcing
            903 => 816,   // Pesangon → Gaji Lain
            909 => 814,   // Helper → Gaji Outsourcing
            937 => 905,   // Lembur → Bonus & Insentif
            1180 => 860,  // Biaya Perbaikan Gedung (Lain-lain) → Perawatan
            2070 => 2250, // Counter Cost → Marketing Digital
            2184 => 2889, // WTC Transport → Toko WTC
            2225 => 847,  // Office Inventories → Perlengkapan Kantor
            2509 => 816,  // Gaji Guru → Gaji Lain
            2640 => 2250, // Collab → Marketing Digital
            2691 => 2250, // Rangers → Marketing Digital
            2724 => 2250, // CleanEat → Marketing Digital
            2729 => 2719, // FitBox JKT → FitBox
            2800 => 2799, // Mesin Pelengkap → Perlengkapan Produksi
            2814 => 829,  // Konsultan Pak Dian → Biaya Konsultasi
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
