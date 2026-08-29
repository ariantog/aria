<?php

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Transaction;
use App\Support\LedgerDuplicateMergePlan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function seedLedger(int $id, string $name, int $parentId = 0): Addrbook
{
    return Addrbook::unguarded(fn () => Addrbook::create([
        'id' => $id,
        'name' => $name,
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $parentId,
    ]));
}

it('merges gedung kendaraan and kantor duplicates without rewriting transactions', function () {
    seedLedger(860, 'Perbaikan & Maintenance Gedung', 13);
    seedLedger(1180, 'Biaya Perbaikan Gedung', 22);
    seedLedger(2959, 'Gedung Cost', 7);
    seedLedger(847, 'Perlengkapan Kantor', 8);
    seedLedger(2225, 'Office Inventories', 8);
    seedLedger(862, 'Biaya Servis Kendaraan', 13);
    seedLedger(2826, 'Biaya Kendaraan', 17);

    $cashOut = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'receiver_id' => 1180,
        'sender_balance' => 5000,
        'receiver_balance' => -2500,
        'total' => -2500,
        'real_total' => -2500,
    ]);

    LedgerDuplicateMergePlan::apply();

    expect(Addrbook::find(860)?->name)->toBe('Biaya Perbaikan Gedung')
        ->and(Addrbook::find(2959)?->name)->toBe('Biaya Sewa Gedung')
        ->and(Addrbook::find(1180))->toBeNull()
        ->and(Addrbook::withTrashed()->find(1180)?->trashed())->toBeTrue()
        ->and(Addrbook::find(2225))->toBeNull()
        ->and(Addrbook::find(2826))->toBeNull()
        ->and(Addrbook::find(847)?->name)->toBe('Perlengkapan Kantor')
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(1180))->toBe(860)
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(2225))->toBe(847)
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(2826))->toBe(862);

    $cashOut->refresh();
    expect($cashOut->receiver_id)->toBe(1180)
        ->and((float) $cashOut->sender_balance)->toBe(5000.0)
        ->and((float) $cashOut->receiver_balance)->toBe(-2500.0);
});

it('is idempotent and skips missing production ids', function () {
    seedLedger(860, 'Biaya Perbaikan Gedung', 13);
    seedLedger(1180, 'Biaya Perbaikan Gedung', 22);

    LedgerDuplicateMergePlan::apply();
    LedgerDuplicateMergePlan::apply();

    expect(LedgerMergeMap::query()->where('old_customer_id', 1180)->count())->toBe(1)
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(1180))->toBe(860)
        ->and(Addrbook::withTrashed()->find(1180)?->trashed())->toBeTrue()
        ->and(Addrbook::find(2826))->toBeNull();
});

it('dry-run does not write merge maps or soft-delete', function () {
    seedLedger(860, 'Perbaikan & Maintenance Gedung', 13);
    seedLedger(1180, 'Biaya Perbaikan Gedung', 22);

    LedgerDuplicateMergePlan::apply(dry: true);

    expect(Addrbook::find(860)?->name)->toBe('Perbaikan & Maintenance Gedung')
        ->and(Addrbook::find(1180))->not->toBeNull()
        ->and(LedgerMergeMap::query()->count())->toBe(0);
});

it('apply-ledger-plan uses the duplicate merge plan', function () {
    seedLedger(2889, 'WTC Cost', 28);
    seedLedger(2184, 'WTC Transport Cost', 17);
    seedLedger(860, 'Perbaikan & Maintenance Gedung', 13);
    seedLedger(1180, 'Biaya Perbaikan Gedung', 22);

    Artisan::call('reporting:apply-ledger-plan');

    expect(Addrbook::find(2889)?->name)->toBe('Biaya Toko WTC')
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(2184))->toBe(2889)
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(1180))->toBe(860);
});

it('merge migration is a no-op when production ledger ids are absent', function () {
    expect(DB::table('ledger_merge_maps')->count())->toBe(0)
        ->and(Addrbook::query()->whereIn('id', [860, 1180, 2959])->count())->toBe(0);
});
