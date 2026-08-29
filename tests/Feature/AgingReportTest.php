<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\AgingReportService;
use App\Services\Reporting\ReportingPeriod;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo(['report-receivables', 'report-payables']);
});

function createAgingTransaction(array $overrides = []): Transaction
{
    $defaults = [
        'date' => '2026-01-15',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => Addrbook::factory()->warehouse()->create()->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => Addrbook::factory()->customer()->create()->id,
        'total' => -100_000,
        'real_total' => -100_000,
        'sender_balance' => 0,
        'receiver_balance' => -100_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    return Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );
}

it('renders receivables and payables pages', function () {
    $this->actingAs($this->user)
        ->get(route('reports.receivables', ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('Piutang Usaha', false)
        ->assertSee('data-testid="receivables-page"', false);

    $this->actingAs($this->user)
        ->get(route('reports.payables', ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('Hutang Usaha', false)
        ->assertSee('data-testid="payables-page"', false);
});

it('forbids users without aging report permissions', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('reports.receivables'))
        ->assertForbidden();

    $this->actingAs($other)
        ->get(route('reports.payables'))
        ->assertForbidden();
});

it('ages receivables from month-end as-of balances not current addrbook stats', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create([
        'name' => 'Customer Aging',
        'payment_due_day' => 15,
    ]);

    createAgingTransaction([
        'date' => '2026-01-10',
        'invoice' => 'SELL-AGE-1',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -100_000,
        'receiver_balance' => -100_000,
    ]);

    AddrbookStat::query()->updateOrCreate(
        ['customer_id' => $customer->id],
        ['balance' => -9_999_999],
    );

    $january = app(AgingReportService::class)->build(
        AgingReportService::KIND_RECEIVABLE,
        2026,
        1,
        AgingReportService::CONSOLIDATED_ENTITY,
    );

    expect($january['as_of'])->toBe('2026-01-31')
        ->and($january['outstanding_total'])->toBe(100_000.0)
        ->and($january['totals']['0-30'])->toBe(100_000.0)
        ->and($january['rows'][0]['name'])->toBe('Customer Aging')
        ->and($january['rows'][0]['invoices'][0]['due_date'])->toBe('2026-02-15');

    $march = app(AgingReportService::class)->build(
        AgingReportService::KIND_RECEIVABLE,
        2026,
        3,
        AgingReportService::CONSOLIDATED_ENTITY,
    );

    expect($march['as_of'])->toBe('2026-03-31')
        ->and($march['totals']['31-60'])->toBe(100_000.0)
        ->and($march['totals']['0-30'])->toBe(0.0);
});

it('puts invoices without payment_due_day into 90+ when old enough', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['name' => 'Old Balance']);

    createAgingTransaction([
        'date' => '2025-10-01',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -40_000,
        'receiver_balance' => -40_000,
    ]);

    $report = app(AgingReportService::class)->build(
        AgingReportService::KIND_RECEIVABLE,
        2026,
        1,
        AgingReportService::CONSOLIDATED_ENTITY,
    );

    expect($report['totals']['90+'])->toBe(40_000.0)
        ->and($report['outstanding_total'])->toBe(40_000.0);
});

it('excludes internal lending from piutang usaha totals', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $lender = Addrbook::factory()->customer()->create([
        'name' => 'Investor Internal',
        'is_internal_lending' => true,
    ]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Usaha Customer']);

    createAgingTransaction([
        'date' => '2026-01-08',
        'sender_id' => $warehouse->id,
        'receiver_id' => $lender->id,
        'total' => -250_000,
        'receiver_balance' => -250_000,
    ]);
    createAgingTransaction([
        'date' => '2026-01-09',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -30_000,
        'receiver_balance' => -30_000,
    ]);

    $report = app(AgingReportService::class)->build(
        AgingReportService::KIND_RECEIVABLE,
        2026,
        1,
        AgingReportService::CONSOLIDATED_ENTITY,
    );

    expect($report['outstanding_total'])->toBe(30_000.0)
        ->and(collect($report['rows'])->pluck('name')->all())
        ->toContain('Usaha Customer')
        ->not->toContain('Investor Internal');
});

it('allocates remaining receivable FIFO across invoices after a cash-in', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $bank = Addrbook::create(['name' => 'Bank Aging', 'type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->customer()->create([
        'name' => 'FIFO Customer',
        'payment_due_day' => 15,
    ]);

    createAgingTransaction([
        'date' => '2026-01-10',
        'invoice' => 'SELL-FIFO-1',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -100_000,
        'receiver_balance' => -100_000,
    ]);
    createAgingTransaction([
        'date' => '2026-02-10',
        'invoice' => 'SELL-FIFO-2',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -50_000,
        'receiver_balance' => -150_000,
    ]);
    createAgingTransaction([
        'date' => '2026-03-05',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'total' => 80_000,
        'real_total' => 80_000,
        'sender_balance' => -70_000,
        'receiver_balance' => 80_000,
    ]);

    $report = app(AgingReportService::class)->build(
        AgingReportService::KIND_RECEIVABLE,
        2026,
        3,
        AgingReportService::CONSOLIDATED_ENTITY,
    );

    $row = collect($report['rows'])->firstWhere('name', 'FIFO Customer');

    expect($report['outstanding_total'])->toBe(70_000.0)
        ->and($row['buckets']['31-60'])->toBe(20_000.0)
        ->and($row['buckets']['0-30'])->toBe(50_000.0);
});

it('treats positive supplier balances as hutang and splits by reporting entity', function () {
    $entityA = ReportingEntity::create(['name' => 'CV Hutang A', 'slug' => 'cv-hutang-a', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'CV Hutang B', 'slug' => 'cv-hutang-b', 'is_pkp' => true]);
    $bankA = Addrbook::create(['name' => 'Bank Hutang A', 'type' => Addrbook::TYPE_BANK]);
    $bankB = Addrbook::create(['name' => 'Bank Hutang B', 'type' => Addrbook::TYPE_BANK]);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $supplierA = Addrbook::factory()->supplier()->create([
        'name' => 'Supplier A',
        'default_bank_id' => $bankA->id,
    ]);
    $supplierB = Addrbook::factory()->supplier()->create([
        'name' => 'Supplier B',
        'default_bank_id' => $bankB->id,
    ]);

    createAgingTransaction([
        'date' => '2026-01-08',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplierA->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'total' => 75_000,
        'real_total' => 75_000,
        'sender_balance' => 75_000,
        'receiver_balance' => 0,
    ]);
    createAgingTransaction([
        'date' => '2026-01-09',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplierB->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'total' => 25_000,
        'real_total' => 25_000,
        'sender_balance' => 25_000,
        'receiver_balance' => 0,
    ]);

    $aging = app(AgingReportService::class);
    $konsolidasi = $aging->build(AgingReportService::KIND_PAYABLE, 2026, 1, AgingReportService::CONSOLIDATED_ENTITY);
    $onlyA = $aging->build(AgingReportService::KIND_PAYABLE, 2026, 1, $entityA->id);
    $onlyB = $aging->build(AgingReportService::KIND_PAYABLE, 2026, 1, $entityB->id);

    expect($konsolidasi['outstanding_total'])->toBe(100_000.0)
        ->and($onlyA['outstanding_total'])->toBe(75_000.0)
        ->and($onlyB['outstanding_total'])->toBe(25_000.0);
});

it('exports receivables aging as csv', function () {
    $customer = Addrbook::factory()->customer()->create(['name' => 'CSV Customer']);
    $warehouse = Addrbook::factory()->warehouse()->create();
    createAgingTransaction([
        'date' => '2026-01-12',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'receiver_balance' => -12_000,
        'total' => -12_000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.receivables', [
            'year' => 2026,
            'month' => 1,
            'export' => 'csv',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())
        ->toContain('Piutang Usaha')
        ->toContain('CSV Customer');
});

it('classifies aging buckets from days overdue', function () {
    $aging = app(AgingReportService::class);

    expect($aging->bucketForDays(0))->toBe('0-30')
        ->and($aging->bucketForDays(30))->toBe('0-30')
        ->and($aging->bucketForDays(31))->toBe('31-60')
        ->and($aging->bucketForDays(90))->toBe('61-90')
        ->and($aging->bucketForDays(91))->toBe('90+')
        ->and($aging->daysOverdue(Carbon::parse('2026-02-15'), Carbon::parse('2026-01-31')))->toBe(0)
        ->and($aging->daysOverdue(Carbon::parse('2026-02-15'), Carbon::parse('2026-03-31')))->toBe(44)
        ->and(ReportingPeriod::asOf(2026, 1, Carbon::parse('2026-08-29'))->toDateString())->toBe('2026-01-31');
});
