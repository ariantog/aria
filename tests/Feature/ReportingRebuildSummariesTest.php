<?php

use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\ReportingTaxAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createReportingTransaction(array $overrides = []): Transaction
{
    $defaults = [
        'date' => '2025-06-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER])->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'total' => 1000,
        'real_total' => 1000,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => User::factory()->create()->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    return Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );
}

it('rebuilds entity and operation summaries from 2025 onward', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $operation = Operation::factory()->create(['name' => 'Marketing', 'report_slug' => 'marketing']);
    $ledger = Addrbook::create([
        'name' => 'Biaya Iklan',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    $cashBank = Addrbook::create(['name' => 'Kas', 'type' => Addrbook::TYPE_BANK]);

    createReportingTransaction([
        'date' => '2024-12-31',
        'receiver_id' => $bank->id,
        'total' => 900,
        'real_total' => 900,
    ]);
    createReportingTransaction([
        'date' => '2025-01-05',
        'receiver_id' => $bank->id,
        'total' => 2500,
        'real_total' => 2500,
    ]);
    createReportingTransaction([
        'date' => '2025-01-05',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $cashBank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $ledger->id,
        'total' => -750,
        'real_total' => -750,
    ]);

    ReportingEntityMonthlySummary::create([
        'year' => 2025,
        'month' => 1,
        'reporting_entity_id' => $entity->id,
        'cash_in' => 999,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    expect(ReportingEntityMonthlySummary::count())->toBe(1)
        ->and((float) ReportingEntityMonthlySummary::first()->cash_in)->toBe(2500.0)
        ->and(ReportingOperationMonthlySummary::count())->toBe(1)
        ->and(ReportingOperationMonthlySummary::first()->report_slug)->toBe('marketing')
        ->and((float) ReportingOperationMonthlySummary::first()->cash_out)->toBe(-750.0);
});

it('records pkp keluaran from sell not customer cash in on rebuild', function () {
    $pkpEntity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $nonPkpEntity = ReportingEntity::create(['name' => 'Pribadi', 'slug' => 'pribadi', 'is_pkp' => false]);
    $pkpBank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $nonPkpBank = Addrbook::create(['name' => 'BCA Pribadi', 'type' => Addrbook::TYPE_BANK]);
    $pkpEntity->banks()->attach($pkpBank->id, ['is_active' => true]);
    $nonPkpEntity->banks()->attach($nonPkpBank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create(['ppn' => true]);
    $warehouse = Addrbook::factory()->warehouse()->create();

    createReportingTransaction([
        'date' => '2025-02-10',
        'sender_id' => $customer->id,
        'receiver_id' => $pkpBank->id,
        'total' => 111_000,
        'real_total' => 111_000,
    ]);
    createReportingTransaction([
        'date' => '2025-02-10',
        'sender_id' => $customer->id,
        'receiver_id' => $nonPkpBank->id,
        'total' => 200_000,
        'real_total' => 200_000,
    ]);
    createReportingTransaction([
        'date' => '2025-02-11',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'SELL-OTHER-INV',
        'total' => -20_000,
        'real_total' => -22_200,
        'ppn' => 2_200,
    ]);
    createReportingTransaction([
        'date' => '2025-02-12',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $pkpBank->id,
        'invoice' => 'PAY-DIFF-INV',
        'total' => 22_200,
        'real_total' => 22_200,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    $pkpSummary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $pkpEntity->id)->first();
    $nonPkpSummary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $nonPkpEntity->id)->first();

    expect($pkpSummary)->not->toBeNull()
        ->and((float) $pkpSummary->ppn_keluaran_dpp)->toBe(20_000.0)
        ->and((float) $pkpSummary->ppn_keluaran_tax)->toBe(2_200.0)
        ->and((float) $pkpSummary->pph_final)->toBe(0.0)
        ->and($nonPkpSummary)->not->toBeNull()
        ->and((float) $nonPkpSummary->pph_final)->toBe(1000.0)
        ->and((float) $nonPkpSummary->ppn_keluaran_tax)->toBe(0.0);
});

it('rolls up buy and sell tax_amount per entity via payment bank', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true]);
    $customer = Addrbook::factory()->customer()->create(['ppn' => true]);
    $warehouse = Addrbook::factory()->warehouse()->create();

    createReportingTransaction([
        'date' => '2025-03-01',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplier->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'invoice' => 'BUY-100',
        'total' => 50_000,
        'real_total' => 55_500,
        'ppn' => 5_500,
    ]);
    createReportingTransaction([
        'date' => '2025-03-02',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $supplier->id,
        'invoice' => 'BUY-100',
        'total' => -55_500,
        'real_total' => -55_500,
    ]);
    createReportingTransaction([
        'date' => '2025-03-05',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'SELL-200',
        'total' => -20_000,
        'real_total' => -22_200,
        'ppn' => 2_200,
    ]);
    createReportingTransaction([
        'date' => '2025-03-06',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'invoice' => 'SELL-200',
        'total' => 22_200,
        'real_total' => 22_200,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $entity->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->ppn_masukan_dpp)->toBe(50_000.0)
        ->and((float) $summary->ppn_masukan_tax)->toBe(5_500.0)
        ->and((float) $summary->ppn_keluaran_dpp)->toBe(20_000.0)
        ->and((float) $summary->ppn_keluaran_tax)->toBe(2_200.0);
});

it('honors reporting_tax_accounts for legacy tax ledger cash out', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $taxLedger = Addrbook::create(['name' => 'PPH Crystal', 'type' => Addrbook::TYPE_ACCOUNT]);
    $operation = Operation::factory()->create(['name' => 'Pajak', 'report_slug' => 'pajak']);
    $taxLedger->update(['parent_id' => $operation->id, 'operation_id' => $operation->id]);

    ReportingTaxAccount::create([
        'legacy_ledger_id' => $taxLedger->id,
        'reporting_entity_id' => $entity->id,
        'tax_type' => 'pph',
    ]);

    createReportingTransaction([
        'date' => '2025-04-01',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $taxLedger->id,
        'total' => -1_500_000,
        'real_total' => -1_500_000,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    expect(ReportingOperationMonthlySummary::count())->toBe(0);

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $entity->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->tax_paid)->toBe(-1_500_000.0);
});

it('resolves legacy tax cash out through ledger merge map', function () {
    $entity = ReportingEntity::create(['name' => 'CV Cipta', 'slug' => 'cv-cipta', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Cipta', 'type' => Addrbook::TYPE_BANK]);
    $canonicalLedger = Addrbook::create(['name' => 'PPH Cipta', 'type' => Addrbook::TYPE_ACCOUNT]);
    $retiredLedger = Addrbook::create(['name' => 'Old PPH Cipta', 'type' => Addrbook::TYPE_ACCOUNT]);

    LedgerMergeMap::create([
        'old_customer_id' => $retiredLedger->id,
        'new_customer_id' => $canonicalLedger->id,
    ]);

    ReportingTaxAccount::create([
        'legacy_ledger_id' => $canonicalLedger->id,
        'reporting_entity_id' => $entity->id,
        'tax_type' => 'pph',
    ]);

    createReportingTransaction([
        'date' => '2025-05-01',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $retiredLedger->id,
        'total' => -250_000,
        'real_total' => -250_000,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $entity->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->tax_paid)->toBe(-250_000.0);
});

it('does not infer pkp keluaran from customer cash in without sell', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    createReportingTransaction([
        'date' => '2025-07-01',
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'invoice' => 'MP-001',
        'total' => 111_000,
        'real_total' => 111_000,
    ]);

    Artisan::call('reporting:rebuild-summaries');

    $summary = ReportingMonthlyTaxSummary::where('reporting_entity_id', $entity->id)->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->ppn_keluaran_dpp)->toBe(0.0)
        ->and((float) $summary->ppn_keluaran_tax)->toBe(0.0);
});

it('installs monthly tax summaries via dedicated migration path', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_24_130000_install_monthly_tax_summaries_table.php',
        '--force' => true,
    ]);

    expect(\Illuminate\Support\Facades\Schema::hasTable('monthly_tax_summaries'))->toBeTrue();
});
