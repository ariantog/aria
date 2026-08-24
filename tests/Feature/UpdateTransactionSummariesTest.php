<?php

use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createCompletedCashIn(array $overrides = []): Transaction
{
    $defaults = [
        'date' => now()->format('Y-m-d'),
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER])->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'total' => 1000,
        'real_total' => 1000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    $transaction = Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );

    UpdateTransactionSummaries::dispatchSync($transaction->id);

    return $transaction->fresh();
}

function createCompletedCashOut(array $overrides = []): Transaction
{
    $defaults = [
        'date' => now()->format('Y-m-d'),
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT])->id,
        'total' => -500,
        'real_total' => -500,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    $transaction = Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );

    UpdateTransactionSummaries::dispatchSync($transaction->id);

    return $transaction->fresh();
}

it('records cash in against the reporting entity for the receiver bank', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $transaction = createCompletedCashIn([
        'receiver_id' => $bank->id,
        'total' => 2500,
        'real_total' => 2500,
    ]);

    $summary = ReportingEntityMonthlySummary::first();

    expect($summary)->not->toBeNull()
        ->and($summary->reporting_entity_id)->toBe($entity->id)
        ->and((float) $summary->cash_in)->toBe(2500.0)
        ->and($summary->year)->toBe((int) $transaction->date->format('Y'))
        ->and($summary->month)->toBe((int) $transaction->date->format('n'));
});

it('dispatches reporting summaries when a cash in transaction is stored', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $this->actingAs($this->user)->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            ['customer_id' => $customer->id, 'total' => 1500],
        ],
    ])->assertRedirect();

    $summary = ReportingEntityMonthlySummary::first();

    expect($summary)->not->toBeNull()
        ->and($summary->reporting_entity_id)->toBe($entity->id)
        ->and((float) $summary->cash_in)->toBe(1500.0);
});

it('rolls cash out to ledger up by the operation report slug', function () {
    $operation = Operation::factory()->create(['name' => 'Marketing', 'report_slug' => 'marketing']);
    $ledger = Addrbook::create([
        'name' => 'Biaya Iklan',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    $bank = Addrbook::create(['name' => 'Kas', 'type' => Addrbook::TYPE_BANK]);

    createCompletedCashOut([
        'sender_id' => $bank->id,
        'receiver_id' => $ledger->id,
        'total' => -750,
        'real_total' => -750,
    ]);

    $summary = ReportingOperationMonthlySummary::first();

    expect($summary)->not->toBeNull()
        ->and($summary->report_slug)->toBe('marketing')
        ->and((float) $summary->cash_out)->toBe(-750.0);
});

it('dispatches operation summaries when a cash out to ledger is stored', function () {
    $operation = Operation::factory()->create(['name' => 'Gaji & Upah', 'report_slug' => 'gaji']);
    $ledger = Addrbook::create([
        'name' => 'Gaji Mingguan',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->actingAs($this->user)->post(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            ['customer_id' => $ledger->id, 'total' => 300],
        ],
    ])->assertRedirect();

    $summary = ReportingOperationMonthlySummary::first();

    expect($summary)->not->toBeNull()
        ->and($summary->report_slug)->toBe('gaji')
        ->and((float) $summary->cash_out)->toBe(-300.0);
});

it('honors ledger merge map when rolling up cash out to ledger', function () {
    $operation = Operation::factory()->create(['name' => 'Biaya Toko', 'report_slug' => 'toko']);
    $canonicalLedger = Addrbook::create([
        'name' => 'Biaya Toko WTC',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    $retiredLedger = Addrbook::create([
        'name' => 'WTC Transport Cost',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    LedgerMergeMap::create([
        'old_customer_id' => $retiredLedger->id,
        'new_customer_id' => $canonicalLedger->id,
    ]);

    $bank = Addrbook::create(['name' => 'Kas', 'type' => Addrbook::TYPE_BANK]);
    createCompletedCashOut([
        'sender_id' => $bank->id,
        'receiver_id' => $retiredLedger->id,
        'total' => -400,
        'real_total' => -400,
    ]);

    expect(ReportingOperationMonthlySummary::count())->toBe(1);

    $summary = ReportingOperationMonthlySummary::first();

    expect($summary->report_slug)->toBe('toko')
        ->and((float) $summary->cash_out)->toBe(-400.0);
});

it('omits transactions before the reporting cutover date', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $operation = Operation::factory()->create(['name' => 'Marketing', 'report_slug' => 'marketing']);
    $ledger = Addrbook::create([
        'name' => 'Biaya Iklan',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    createCompletedCashIn([
        'date' => '2024-12-31',
        'receiver_id' => $bank->id,
    ]);
    createCompletedCashOut([
        'date' => '2024-12-31',
        'receiver_id' => $ledger->id,
    ]);

    expect(ReportingEntityMonthlySummary::count())->toBe(0)
        ->and(ReportingOperationMonthlySummary::count())->toBe(0);
});

it('skips cash in from internal ledger transfers for entity summaries', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $ledger = Addrbook::create(['name' => 'Transfer Pending', 'type' => Addrbook::TYPE_ACCOUNT]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    createCompletedCashIn([
        'sender_type' => Addrbook::TYPE_ACCOUNT,
        'sender_id' => $ledger->id,
        'receiver_id' => $bank->id,
    ]);

    expect(ReportingEntityMonthlySummary::count())->toBe(0);
});

it('skips cash out to non-ledger contacts for operation summaries', function () {
    $bank = Addrbook::create(['name' => 'Kas', 'type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::create(['name' => 'Customer Refund', 'type' => Addrbook::TYPE_CUSTOMER]);

    createCompletedCashOut([
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
    ]);

    expect(ReportingOperationMonthlySummary::count())->toBe(0);
});
