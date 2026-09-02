<?php

use App\Models\Produksi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function migrateProduksiWorkerIdsNullable(): void
{
    DB::table('migrations')
        ->where('migration', '2026_09_02_120000_make_produksi_worker_ids_nullable')
        ->delete();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_09_02_120000_make_produksi_worker_ids_nullable.php',
        '--force' => true,
    ]);
}

it('converts zero produksi worker ids to null and is safe to re-run', function () {
    $produksi = Produksi::withoutEvents(fn () => Produksi::create([
        'temp_name' => 'Zero Worker Ids',
        'quantity' => 4,
        'status' => Produksi::STATUS_PRODUKSI,
        'jahit_id' => 0,
        'qc_id' => 0,
        'pritil_id' => 0,
    ]));

    expect((int) $produksi->fresh()->qc_id)->toBe(0);

    migrateProduksiWorkerIdsNullable();
    migrateProduksiWorkerIdsNullable();

    $produksi->refresh();

    expect($produksi->jahit_id)->toBeNull()
        ->and($produksi->qc_id)->toBeNull()
        ->and($produksi->pritil_id)->toBeNull();
});
