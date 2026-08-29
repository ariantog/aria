<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\ReportingBalanceSnapshot;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyInventoryValue;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\BalanceAsOfService;
use App\Services\Reporting\NeracaService;
use App\Services\Reporting\ReportingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-neraca');
});

function createBalanceTransaction(array $overrides = []): Transaction
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
        'sender_balance' => 0,
        'receiver_balance' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    return Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );
}

it('renders the neraca page for an authorized user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.neraca', ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('Neraca', false)
        ->assertSee('Persediaan', false)
        ->assertSee('data-testid="neraca-page"', false);
});

it('forbids users without report-neraca permission', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('reports.neraca'))
        ->assertForbidden();
});

it('uses replayed running balances as of month-end instead of current addrbook stats', function () {
    $bank = Addrbook::create(['name' => 'BCA Replay', 'type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Customer Replay']);

    createBalanceTransaction([
        'date' => '2026-01-10',
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'sender_balance' => -1_000_000,
        'receiver_balance' => 1_000_000,
    ]);

    createBalanceTransaction([
        'date' => '2026-02-10',
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 500_000,
        'real_total' => 500_000,
        'sender_balance' => -500_000,
        'receiver_balance' => 1_500_000,
    ]);

    AddrbookStat::query()->updateOrCreate(
        ['customer_id' => $bank->id],
        ['balance' => 9_999_999],
    );
    AddrbookStat::query()->updateOrCreate(
        ['customer_id' => $customer->id],
        ['balance' => -9_999_999],
    );

    $january = app(NeracaService::class)->build(2026, 1, NeracaService::CONSOLIDATED_ENTITY);

    expect($january['as_of'])->toBe('2026-01-31')
        ->and($january['source'])->toBeIn(['replay', 'snapshot'])
        ->and($january['aktiva_lancar']['kas'])->toBe(1_000_000.0)
        ->and($january['aktiva_lancar']['piutang'])->toBe(1_000_000.0)
        ->and($january['aktiva_lancar']['kas'])->not->toBe(9_999_999.0);

    $february = app(NeracaService::class)->build(2026, 2, NeracaService::CONSOLIDATED_ENTITY);
    expect($february['aktiva_lancar']['kas'])->toBe(1_500_000.0)
        ->and($february['aktiva_lancar']['piutang'])->toBe(500_000.0);
});

it('splits kas by reporting entity and sums them on konsolidasi', function () {
    $entityA = ReportingEntity::create(['name' => 'CV A', 'slug' => 'cv-a-neraca', 'is_pkp' => true, 'modal' => 10_000]);
    $entityB = ReportingEntity::create(['name' => 'CV B', 'slug' => 'cv-b-neraca', 'is_pkp' => true, 'modal' => 40_000]);
    $bankA = Addrbook::create(['name' => 'Bank A', 'type' => Addrbook::TYPE_BANK]);
    $bankB = Addrbook::create(['name' => 'Bank B', 'type' => Addrbook::TYPE_BANK]);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create();

    createBalanceTransaction([
        'date' => '2026-01-05',
        'sender_id' => $customer->id,
        'receiver_id' => $bankA->id,
        'receiver_balance' => 200_000,
        'sender_balance' => 0,
    ]);
    createBalanceTransaction([
        'date' => '2026-01-06',
        'sender_id' => $customer->id,
        'receiver_id' => $bankB->id,
        'receiver_balance' => 50_000,
        'sender_balance' => 0,
    ]);

    $neraca = app(NeracaService::class);
    $konsolidasi = $neraca->build(2026, 1, NeracaService::CONSOLIDATED_ENTITY);
    $onlyA = $neraca->build(2026, 1, $entityA->id);
    $onlyB = $neraca->build(2026, 1, $entityB->id);

    expect($konsolidasi['aktiva_lancar']['kas'])->toBe(250_000.0)
        ->and($onlyA['aktiva_lancar']['kas'])->toBe(200_000.0)
        ->and($onlyB['aktiva_lancar']['kas'])->toBe(50_000.0)
        ->and($konsolidasi['ekuitas']['modal'])->toBe(50_000.0)
        ->and($onlyA['ekuitas']['modal'])->toBe(10_000.0)
        ->and($onlyB['ekuitas']['modal'])->toBe(40_000.0)
        ->and($onlyA['aktiva_lancar']['persediaan'])->toBe(0.0)
        ->and($konsolidasi['total_aktiva'])->toBe($konsolidasi['total_pasiva']);
});

it('treats positive supplier balances as hutang and plugs laba ditahan', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Supplier Hutang']);
    $warehouse = Addrbook::factory()->warehouse()->create();

    createBalanceTransaction([
        'date' => '2026-01-08',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplier->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'total' => 75_000,
        'real_total' => 75_000,
        'sender_balance' => 75_000,
        'receiver_balance' => 0,
    ]);

    $report = app(NeracaService::class)->build(2026, 1, NeracaService::CONSOLIDATED_ENTITY);

    expect($report['kewajiban']['hutang_usaha'])->toBe(75_000.0)
        ->and($report['total_aktiva'])->toBe($report['total_pasiva'])
        ->and(abs($report['balance_check']))->toBeLessThan(0.01);
});

it('includes persisted persediaan closing on konsolidasi and excludes it per entity', function () {
    Setting::query()->updateOrCreate(
        ['slug' => 'reporting.persediaan_awal'],
        ['group' => 'Reporting', 'name' => 'Persediaan Awal (Jan 2026)', 'value' => 250_000],
    );
    $entity = ReportingEntity::create(['name' => 'CV Modal', 'slug' => 'cv-modal-neraca', 'is_pkp' => true, 'modal' => 1]);

    $konsolidasi = app(NeracaService::class)->build(2026, 1, NeracaService::CONSOLIDATED_ENTITY);
    $perEntity = app(NeracaService::class)->build(2026, 1, $entity->id);

    expect($konsolidasi['persediaan']['opening'])->toBe(250_000.0)
        ->and($konsolidasi['persediaan']['closing'])->toBe(250_000.0)
        ->and($konsolidasi['aktiva_lancar']['persediaan'])->toBe(250_000.0)
        ->and($perEntity['aktiva_lancar']['persediaan'])->toBe(0.0)
        ->and($perEntity['persediaan']['closing'])->toBe(250_000.0);

    expect(ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 1)->exists())->toBeTrue();
});

it('persists balance snapshots from running balances and can rebuild via artisan', function () {
    $bank = Addrbook::create(['name' => 'Snapshot Bank', 'type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->customer()->create();

    createBalanceTransaction([
        'date' => '2026-01-20',
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'receiver_balance' => 88_000,
        'sender_balance' => -12_000,
    ]);

    Artisan::call('reporting:snapshot-balances', ['--date' => '2026-01-31']);

    $snap = ReportingBalanceSnapshot::query()
        ->whereDate('as_of_date', '2026-01-31')
        ->where('customer_id', $bank->id)
        ->first();

    expect($snap)->not->toBeNull()
        ->and((float) $snap->balance)->toBe(88_000.0);

    $fromSnapshot = app(BalanceAsOfService::class)->balancesAsOf(Carbon::parse('2026-01-31'));
    expect($fromSnapshot->firstWhere('customer_id', $bank->id)->balance)->toBe(88_000.0);
});

it('uses month-end as-of dates for past months', function () {
    expect(ReportingPeriod::asOf(2026, 1, Carbon::parse('2026-08-29'))->toDateString())->toBe('2026-01-31')
        ->and(ReportingPeriod::asOf(2026, 8, Carbon::parse('2026-08-29'))->toDateString())->toBe('2026-08-29');
});

it('returns an illuminate carbon instance for the current month', function () {
    $asOf = ReportingPeriod::asOf((int) now()->year, (int) now()->month);

    expect($asOf)->toBeInstanceOf(Carbon::class);
});
