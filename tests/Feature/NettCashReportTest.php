<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\NettCashService;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-nett-cash');
});

function createNettCashTransaction(array $overrides = []): Transaction
{
    $defaults = [
        'date' => '2026-04-10',
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

it('renders nett cash for an authorized user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.nett-cash-sby', ['year' => 2026, 'month' => 4]))
        ->assertOk()
        ->assertSee('Nett Cash', false)
        ->assertSee('data-testid="nett-cash-page"', false);
});

it('forbids users without report-nett-cash permission', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('reports.nett-cash-sby'))
        ->assertForbidden();
});

it('lists every customer and reseller with cash in without hardcoded ids', function () {
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'Bank SBY']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko Baru Tanpa Hardcode']);
    $reseller = Addrbook::create(['name' => 'Reseller Baru', 'type' => Addrbook::TYPE_RESELLER]);
    $quiet = Addrbook::factory()->customer()->create(['name' => 'Tidak Ada Transaksi']);

    createNettCashTransaction([
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 2_500_000,
        'invoice' => 'CIN-CUST-1',
    ]);
    createNettCashTransaction([
        'sender_type' => Addrbook::TYPE_RESELLER,
        'sender_id' => $reseller->id,
        'receiver_id' => $bank->id,
        'total' => 750_000,
        'invoice' => 'CIN-RES-1',
    ]);

    $report = app(NettCashService::class)->build(2026, 4, NettCashService::CONSOLIDATED_ENTITY);

    expect($report['totals']['cash_in'])->toBe(3_250_000.0)
        ->and($report['totals']['customer_cash_in'])->toBe(2_500_000.0)
        ->and($report['totals']['reseller_cash_in'])->toBe(750_000.0)
        ->and(collect($report['rows'])->pluck('id')->all())
        ->toContain($customer->id)
        ->toContain($reseller->id)
        ->not->toContain($quiet->id);

    $this->actingAs($this->user)
        ->get(route('reports.nett-cash-sby', ['year' => 2026, 'month' => 4]))
        ->assertOk()
        ->assertSee('Toko Baru Tanpa Hardcode', false)
        ->assertSee('Reseller Baru', false)
        ->assertDontSee('Tidak Ada Transaksi', false)
        ->assertSee('data-testid="nett-cash-row-'.$customer->id.'"', false);
});

it('excludes pending cash in and internal lending from the bonus total', function () {
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko Usaha']);
    $lender = Addrbook::factory()->customer()->create([
        'name' => 'Pinjaman Internal',
        'is_internal_lending' => true,
    ]);

    createNettCashTransaction([
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 1_200_000,
    ]);
    createNettCashTransaction([
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 400_000,
        'status' => Transaction::STATUS_PENDING,
        'invoice' => 'CIN-PENDING',
    ]);
    createNettCashTransaction([
        'sender_id' => $lender->id,
        'receiver_id' => $bank->id,
        'total' => 800_000,
        'invoice' => 'CIN-LEND',
    ]);

    $report = app(NettCashService::class)->build(2026, 4, NettCashService::CONSOLIDATED_ENTITY);

    expect($report['totals']['cash_in'])->toBe(1_200_000.0)
        ->and($report['lending_total'])->toBe(800_000.0)
        ->and(collect($report['lending_rows'])->pluck('name')->all())->toContain('Pinjaman Internal');

    $this->actingAs($this->user)
        ->get(route('reports.nett-cash-sby', ['year' => 2026, 'month' => 4]))
        ->assertOk()
        ->assertSee('Toko Usaha', false)
        ->assertSee('data-testid="nett-cash-lending"', false)
        ->assertSee('Pinjaman Internal', false);
});

it('filters cash in by reporting entity banks', function () {
    $entityA = ReportingEntity::create(['name' => 'CV SBY', 'slug' => 'cv-sby-nett', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'CV JKT', 'slug' => 'cv-jkt-nett', 'is_pkp' => true]);
    $bankA = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'Bank SBY']);
    $bankB = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'Bank JKT']);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);

    $customerA = Addrbook::factory()->customer()->create(['name' => 'Toko SBY']);
    $customerB = Addrbook::factory()->customer()->create(['name' => 'Toko JKT']);

    createNettCashTransaction([
        'sender_id' => $customerA->id,
        'receiver_id' => $bankA->id,
        'total' => 900_000,
    ]);
    createNettCashTransaction([
        'sender_id' => $customerB->id,
        'receiver_id' => $bankB->id,
        'total' => 300_000,
    ]);

    $sby = app(NettCashService::class)->build(2026, 4, $entityA->id);
    $all = app(NettCashService::class)->build(2026, 4, NettCashService::CONSOLIDATED_ENTITY);

    expect($sby['totals']['cash_in'])->toBe(900_000.0)
        ->and(collect($sby['rows'])->pluck('name')->all())->toBe(['Toko SBY'])
        ->and($all['totals']['cash_in'])->toBe(1_200_000.0);
});

it('exports csv of the bonus rows', function () {
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko CSV']);
    createNettCashTransaction([
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 111_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.nett-cash-sby', ['year' => 2026, 'month' => 4, 'export' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertSee('Toko CSV', false)
        ->assertSee('111000', false);
});
