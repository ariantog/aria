<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLedgerPlanCommand extends Command
{
    protected $signature = 'reporting:apply-ledger-plan {--dry-run : Show changes without applying}';

    protected $description = 'Rename key ledgers, merge maps, and soft-delete obsolete accounts per reporting plan';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $renames = [
            2889 => ['name' => 'Biaya Toko WTC'],
            2842 => ['name' => 'Biaya Toko Citos'],
            2273 => ['name' => 'Biaya Tokopedia'],
            2099 => ['name' => 'Biaya Metro'],
            2178 => ['name' => 'Biaya Sogo'],
            2633 => ['name' => 'Biaya Central'],
            2959 => ['name' => 'Biaya Sewa Gedung'],
        ];

        $merges = [
            2184 => 2889, // WTC Transport → WTC Toko
            2844 => 2842, // Sewa Citos → Citos Toko
            2854 => 2842, // FX Cost → Citos Toko
        ];

        $softDelete = [817, 1644, 2731]; // Gaji Harian, Plotter, Pendapatan FitBox

        if ($dry) {
            $this->info('Dry run — no changes applied.');
        }

        DB::transaction(function () use ($dry, $renames, $merges, $softDelete) {
            foreach ($renames as $id => $data) {
                $a = Addrbook::find($id);
                if (! $a) {
                    continue;
                }
                $this->line("Rename {$id}: {$a->name} → {$data['name']}");
                if (! $dry) {
                    $a->update($data);
                }
            }

            foreach ($merges as $oldId => $newId) {
                if (! Addrbook::find($oldId) || ! Addrbook::find($newId)) {
                    continue;
                }
                $this->line("Merge map {$oldId} → {$newId}");
                if (! $dry) {
                    LedgerMergeMap::updateOrCreate(
                        ['old_customer_id' => $oldId],
                        ['new_customer_id' => $newId],
                    );
                    Addrbook::where('id', $oldId)->delete();
                }
            }

            foreach ($softDelete as $id) {
                $a = Addrbook::find($id);
                if (! $a) {
                    continue;
                }
                $this->line("Soft-delete {$id}: {$a->name}");
                if (! $dry) {
                    $a->delete();
                }
            }
        });

        $this->info($dry ? 'Dry run complete.' : 'Ledger plan applied.');

        return self::SUCCESS;
    }
}
