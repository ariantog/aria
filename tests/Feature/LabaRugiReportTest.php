<?php

use App\Enums\ItemType;
use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\LabaRugiService;
use App\Services\Reporting\ReportingPeriod;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-laba-rugi');
});

function seedLabaRugiEntities(): array
{
    $entityA = ReportingEntity::create(['name' => 'CV Laba A', 'slug' => 'cv-laba-a', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'CV Laba B', 'slug' => 'cv-laba-b', 'is_pkp' => false]);
    $bankA = Addrbook::create(['name' => 'Bank Laba A', 'type' => Addrbook::TYPE_BANK]);
    $bankB = Addrbook::create(['name' => 'Bank Laba B', 'type' => Addrbook::TYPE_BANK]);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);

    return compact('entityA', 'entityB', 'bankA', 'bankB');
}

function createLabaRugiTransaction(array $overrides = []): Transaction
{
    $defaults = [
        'date' => '2026-01-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => Addrbook::factory()->customer()->create()->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    return Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );
}

it('renders the laba rugi page for an authorized user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.laba-rugi', ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('Laporan Laba Rugi', false)
        ->assertSee('data-testid="laba-rugi-page"', false)
        ->assertSee('Cash In/Out bank, bukan saldo kontak', false);
});

it('forbids users without report-laba-rugi permission', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('reports.laba-rugi'))
        ->assertForbidden();
});

it('uses live bank cash-in and excludes internal lending and stale monthly summaries', function () {
    $data = seedLabaRugiEntities();
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko Regular']);
    $other = Addrbook::factory()->customer()->create(['name' => 'Toko B']);
    $lender = Addrbook::factory()->customer()->create([
        'name' => 'Pinjaman Internal',
        'is_internal_lending' => true,
    ]);

    ReportingEntityMonthlySummary::create([
        'year' => 2026,
        'month' => 1,
        'reporting_entity_id' => $data['entityA']->id,
        'cash_in' => 9_999_999,
    ]);
    ReportingEntityMonthlySummary::create([
        'year' => 2026,
        'month' => 1,
        'reporting_entity_id' => $data['entityB']->id,
        'cash_in' => 500_000,
    ]);

    createLabaRugiTransaction([
        'date' => '2026-01-12',
        'sender_id' => $customer->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 1_600_000,
        'real_total' => 1_600_000,
        'invoice' => 'CIN-REG-1',
    ]);
    createLabaRugiTransaction([
        'date' => '2026-01-13',
        'sender_id' => $lender->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 400_000,
        'real_total' => 400_000,
        'invoice' => 'CIN-LEND-1',
    ]);
    createLabaRugiTransaction([
        'date' => '2026-01-14',
        'sender_id' => $other->id,
        'receiver_id' => $data['bankB']->id,
        'total' => 250_000,
        'real_total' => 250_000,
        'invoice' => 'CIN-B-1',
    ]);

    $service = app(LabaRugiService::class);
    $konsolidasi = $service->build(2026, 1, 1, LabaRugiService::CONSOLIDATED_ENTITY);
    $onlyA = $service->build(2026, 1, 1, $data['entityA']->id);
    $onlyB = $service->build(2026, 1, 1, $data['entityB']->id);

    expect($konsolidasi['source'])->toBe('bank_cash')
        ->and($konsolidasi['pendapatan_total'])->toBe(1_850_000.0)
        ->and($konsolidasi['internal_lending_total'])->toBe(400_000.0)
        ->and($konsolidasi['laba_usaha_total'])->toBe(1_850_000.0)
        ->and($konsolidasi['pendapatan_total'])->not->toBe(9_999_999.0)
        ->and($onlyA['pendapatan_total'])->toBe(1_600_000.0)
        ->and($onlyB['pendapatan_total'])->toBe(250_000.0)
        ->and(collect($konsolidasi['drilldown']['pendapatan'])->pluck('invoice')->all())
        ->toContain('CIN-REG-1')
        ->toContain('CIN-B-1')
        ->not->toContain('CIN-LEND-1');
});

it('takes HPP from inventory roll-forward cogs on konsolidasi only', function () {
    $data = seedLabaRugiEntities();
    $item = Item::factory()->create(['type' => ItemType::ITEM, 'cost' => 5_000]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    createLabaRugiTransaction([
        'date' => '2026-01-10',
        'sender_id' => $customer->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 100_000,
        'real_total' => 100_000,
    ]);

    $sell = Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2026-01-18',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -30_000,
        'real_total' => -30_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));
    $sell->details()->create([
        'date' => '2026-01-18',
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 3,
        'price' => 10_000,
        'total' => 30_000,
        'discount' => 0,
    ]);

    $service = app(LabaRugiService::class);
    $konsolidasi = $service->build(2026, 1, 1, LabaRugiService::CONSOLIDATED_ENTITY);
    $perEntity = $service->build(2026, 1, 1, $data['entityA']->id);

    expect($konsolidasi['hpp_total'])->toBe(15_000.0)
        ->and($konsolidasi['laba_kotor_total'])->toBe(85_000.0)
        ->and($perEntity['hpp_total'])->toBe(0.0)
        ->and($perEntity['laba_kotor_total'])->toBe(100_000.0);
});

it('excludes production-cost ledger cash-out from beban usaha', function () {
    $data = seedLabaRugiEntities();
    $marketingOp = Operation::factory()->create(['name' => 'Marketing Umum', 'report_slug' => 'marketing']);
    $gajiOp = Operation::factory()->create(['name' => 'Gaji & Upah', 'report_slug' => 'gaji']);
    $iklan = Addrbook::create([
        'name' => 'Biaya Iklan LR',
        'type' => Addrbook::TYPE_ACCOUNT,
        'operation_id' => $marketingOp->id,
    ]);
    $gaji = Addrbook::create([
        'name' => 'Gaji Mingguan LR',
        'type' => Addrbook::TYPE_ACCOUNT,
        'operation_id' => $gajiOp->id,
    ]);
    ReportingLedgerRoleModel::create(['customer_id' => $gaji->id, 'role' => ReportingLedgerRole::ProductionCost]);

    ReportingOperationMonthlySummary::create([
        'year' => 2026,
        'month' => 1,
        'report_slug' => 'marketing',
        'cash_out' => -9_999_999,
    ]);
    ReportingOperationMonthlySummary::create([
        'year' => 2026,
        'month' => 1,
        'report_slug' => 'gaji',
        'cash_out' => -80_000,
    ]);

    createLabaRugiTransaction([
        'date' => '2026-01-20',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bankA']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $iklan->id,
        'total' => -200_000,
        'real_total' => -200_000,
    ]);
    createLabaRugiTransaction([
        'date' => '2026-01-21',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bankA']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $gaji->id,
        'total' => -80_000,
        'real_total' => -80_000,
    ]);

    $report = app(LabaRugiService::class)->build(2026, 1, 1, LabaRugiService::CONSOLIDATED_ENTITY);
    $slugs = collect($report['beban'])->pluck('total', 'slug');

    expect($report['beban_grand_total'])->toBe(200_000.0)
        ->and($slugs['marketing'])->toBe(200_000.0)
        ->and($slugs->has('gaji'))->toBeFalse();
});

it('adds pph final and tax paid from monthly tax summaries', function () {
    $data = seedLabaRugiEntities();

    ReportingMonthlyTaxSummary::create([
        'year' => 2026,
        'month' => 1,
        'reporting_entity_id' => $data['entityB']->id,
        'pph_final' => 2_500,
        'tax_paid' => 1_000,
    ]);

    $report = app(LabaRugiService::class)->build(2026, 1, 1, $data['entityB']->id);

    expect($report['pajak_total'])->toBe(3_500.0)
        ->and($report['laba_bersih_total'])->toBe(-3_500.0);
});

it('sums a 3-month period ending at the selected month', function () {
    $data = seedLabaRugiEntities();
    $customer = Addrbook::factory()->customer()->create();

    createLabaRugiTransaction([
        'date' => '2025-12-15',
        'sender_id' => $customer->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 100_000,
        'real_total' => 100_000,
    ]);
    createLabaRugiTransaction([
        'date' => '2026-01-15',
        'sender_id' => $customer->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 200_000,
        'real_total' => 200_000,
    ]);
    createLabaRugiTransaction([
        'date' => '2026-02-15',
        'sender_id' => $customer->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 50_000,
        'real_total' => 50_000,
    ]);

    expect(ReportingPeriod::monthsEnding(2026, 2, 3))->toBe([
        [2025, 12],
        [2026, 1],
        [2026, 2],
    ]);

    $report = app(LabaRugiService::class)->build(2026, 2, 3, $data['entityA']->id);

    expect($report['months'])->toBe(3)
        ->and($report['month_keys'])->toBe(['2025-12', '2026-01', '2026-02'])
        ->and($report['pendapatan']['2025-12'])->toBe(100_000.0)
        ->and($report['pendapatan']['2026-01'])->toBe(200_000.0)
        ->and($report['pendapatan_total'])->toBe(350_000.0);
});

it('exports laba rugi as csv', function () {
    $data = seedLabaRugiEntities();
    createLabaRugiTransaction([
        'date' => '2026-01-08',
        'sender_id' => Addrbook::factory()->customer()->create()->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 75_000,
        'real_total' => 75_000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.laba-rugi', [
            'year' => 2026,
            'month' => 1,
            'months' => 1,
            'entity' => $data['entityA']->id,
            'export' => 'csv',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)
        ->toContain('Laporan Laba Rugi')
        ->toContain('Pendapatan usaha')
        ->toContain('Laba bersih');
});
