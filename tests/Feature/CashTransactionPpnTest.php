<?php

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reporting\TaxReportService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->testDate = now()->format('Y-m-d');
});

function createPkpCashOutFixture(): array
{
    $entity = ReportingEntity::create([
        'name' => 'PT PKP Test',
        'slug' => 'pt-pkp-test',
        'is_pkp' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA PKP', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $operation = Operation::factory()->create(['name' => 'Sewa', 'report_slug' => 'sewa']);
    $ledger = Addrbook::create([
        'name' => 'Citos Cost',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    return compact('entity', 'bank', 'ledger');
}

it('auto-calculates citos rental tax breakdown when pph switch is on', function () {
    $data = createPkpCashOutFixture();

    $this->actingAs($this->user)->postJson(route('transactions.cash-out.store'), [
        'date' => $this->testDate,
        'account_id' => $data['bank']->id,
        'items' => [[
            'customer_id' => $data['ledger']->id,
            'total' => 17_422_500,
            'record_ppn' => true,
            'record_pph' => true,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::query()->latest('id')->first();

    expect((float) $transaction->total)->toBe(-17_422_500.0)
        ->and((float) $transaction->ppn_dpp)->toBe(17_250_000.0)
        ->and((float) $transaction->ppn)->toBe(1_897_500.0)
        ->and((float) $transaction->pph)->toBe(1_725_000.0);

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $data['entity']->id)->first();

    expect((float) $summary->ppn_masukan_dpp)->toBe(17_250_000.0)
        ->and((float) $summary->ppn_masukan_tax)->toBe(1_897_500.0);
});

it('auto-calculates PPN from gross total when switch is on without manual amounts', function () {
    $data = createPkpCashOutFixture();
    $gross = 1_110_000;

    $this->actingAs($this->user)->postJson(route('transactions.cash-out.store'), [
        'date' => $this->testDate,
        'account_id' => $data['bank']->id,
        'items' => [[
            'customer_id' => $data['ledger']->id,
            'total' => $gross,
            'record_ppn' => true,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::query()->latest('id')->first();

    expect((float) $transaction->ppn_dpp)->toBe(1_000_000.0)
        ->and((float) $transaction->ppn)->toBe(110_000.0);
});

it('stores cash out with explicit PPN masukan when row switch is on', function () {
    $data = createPkpCashOutFixture();

    $this->actingAs($this->user)->postJson(route('transactions.cash-out.store'), [
        'date' => $this->testDate,
        'account_id' => $data['bank']->id,
        'items' => [[
            'customer_id' => $data['ledger']->id,
            'total' => 17_422_500,
            'record_ppn' => true,
            'ppn_dpp' => 17_250_000,
            'ppn' => 1_897_500,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->total)->toBe(-17_422_500.0)
        ->and((float) $transaction->ppn)->toBe(1_897_500.0)
        ->and((float) $transaction->ppn_dpp)->toBe(17_250_000.0);

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $data['entity']->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->ppn_masukan_dpp)->toBe(17_250_000.0)
        ->and((float) $summary->ppn_masukan_tax)->toBe(1_897_500.0);
});

it('does not record PPN when the cash out row switch is off', function () {
    $data = createPkpCashOutFixture();

    $this->actingAs($this->user)->postJson(route('transactions.cash-out.store'), [
        'date' => $this->testDate,
        'account_id' => $data['bank']->id,
        'items' => [[
            'customer_id' => $data['ledger']->id,
            'total' => 500_000,
            'record_ppn' => false,
        ]],
    ])->assertRedirect();

    expect(ReportingMonthlyTaxSummary::count())->toBe(0);
});

it('rejects PPN on cash out when bank is not a PKP entity bank', function () {
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $ledger = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT]);

    $this->actingAs($this->user)->postJson(route('transactions.cash-out.store'), [
        'date' => $this->testDate,
        'account_id' => $bank->id,
        'items' => [[
            'customer_id' => $ledger->id,
            'total' => 100_000,
            'record_ppn' => true,
            'ppn_dpp' => 90_000,
            'ppn' => 9_900,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.record_ppn']);
});

it('records cash in explicit PPN keluaran and skips gross inference', function () {
    $entity = ReportingEntity::create([
        'name' => 'PT PKP Cash In',
        'slug' => 'pt-pkp-cash-in',
        'is_pkp' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA PKP In', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $supplier = Addrbook::create(['name' => 'Vendor', 'type' => Addrbook::TYPE_SUPPLIER]);

    $this->actingAs($this->user)->postJson(route('transactions.cash-in.store'), [
        'date' => $this->testDate,
        'account_id' => $bank->id,
        'items' => [[
            'customer_id' => $supplier->id,
            'total' => 5_000_000,
            'record_ppn' => true,
            'ppn_dpp' => 4_504_504.5,
            'ppn' => 495_495.5,
        ]],
    ])->assertRedirect();

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $entity->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->ppn_keluaran_dpp)->toBe(4_504_504.5)
        ->and((float) $summary->ppn_keluaran_tax)->toBe(495_495.5);
});

it('updates cash transaction PPN via show page endpoint', function () {
    $data = createPkpCashOutFixture();

    $transaction = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => $this->testDate,
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bank']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['ledger']->id,
        'total' => -17_422_500,
        'real_total' => -17_422_500,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    $this->actingAs($this->user)->patchJson(route('transactions.update-ppn', $transaction), [
        'record_ppn' => true,
        'ppn_dpp' => 17_250_000,
        'ppn' => 1_897_500,
    ])->assertOk()
        ->assertJsonPath('record_ppn', true)
        ->assertJsonPath('ppn', 1897500);

    $transaction->refresh();
    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $data['entity']->id)->first();

    expect((float) $transaction->ppn)->toBe(1_897_500.0)
        ->and((float) $summary->ppn_masukan_tax)->toBe(1_897_500.0);
});

it('includes explicit cash out rows in PPN masukan report drill-down', function () {
    $data = createPkpCashOutFixture();

    $transaction = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => $this->testDate,
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bank']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['ledger']->id,
        'total' => -17_422_500,
        'real_total' => -17_422_500,
        'ppn' => 1_897_500,
        'ppn_dpp' => 17_250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    UpdateTransactionSummaries::dispatchSync($transaction->id);

    $rows = app(TaxReportService::class)->masukanRows(
        (int) now()->format('Y'),
        (int) now()->format('n'),
        $data['entity']->id,
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['type'])->toBe('cash_out')
        ->and($rows->first()['dpp'])->toBe(17_250_000.0)
        ->and($rows->first()['ppn'])->toBe(1_897_500.0);
});

it('adjusts tax summary when PPN is cleared on edit', function () {
    $data = createPkpCashOutFixture();

    $transaction = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => $this->testDate,
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bank']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['ledger']->id,
        'total' => -100_000,
        'real_total' => -100_000,
        'ppn' => 11_000,
        'ppn_dpp' => 100_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    UpdateTransactionSummaries::dispatchSync($transaction->id);

    $this->actingAs($this->user)->patchJson(route('transactions.update-ppn', $transaction), [
        'record_ppn' => false,
    ])->assertOk();

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $data['entity']->id)->first();

    expect((float) $summary->ppn_masukan_tax)->toBe(0.0)
        ->and((float) $summary->ppn_masukan_dpp)->toBe(0.0);
});
