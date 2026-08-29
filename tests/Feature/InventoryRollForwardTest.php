<?php

use App\Enums\ItemType;
use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\Borongan;
use App\Models\Item;
use App\Models\Produksi;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingMonthlyInventoryValue;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Worker;
use App\Services\Reporting\InventoryRollForwardService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->inventory = app(InventoryRollForwardService::class);
});

function seedPersediaanAwal(float $amount): void
{
    Setting::query()->updateOrCreate(
        ['slug' => 'reporting.persediaan_awal'],
        ['group' => 'Reporting', 'name' => 'Persediaan Awal (Jan 2026)', 'value' => $amount],
    );
}

function createInventoryTransaction(array $header, array $detail = []): Transaction
{
    $defaults = [
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'discount' => 0,
        'ppn' => 0,
    ];

    $transaction = Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $header)),
    );

    if ($detail !== []) {
        $transaction->details()->create(array_merge([
            'date' => $transaction->date,
            'transaction_type' => $transaction->type,
            'sender_id' => $transaction->sender_id,
            'receiver_id' => $transaction->receiver_id,
            'discount' => 0,
        ], $detail));
    }

    return $transaction;
}

it('starts the persediaan roll-forward in january 2026 from the seed setting', function () {
    seedPersediaanAwal(1_000_000);

    $january = $this->inventory->forMonth(2026, 1);

    expect($january['opening'])->toBe(1_000_000.0)
        ->and($january['closing'])->toBe(1_000_000.0)
        ->and($this->inventory->isBeforeStart(2025, 12))->toBeTrue()
        ->and($this->inventory->forMonth(2025, 12)['opening'])->toBe(0.0)
        ->and($this->inventory->forMonth(2025, 12)['closing'])->toBe(0.0);
});

it('adds material buys and subtracts sell cogs, gaji mingguan, and material cash-out', function () {
    seedPersediaanAwal(100_000);

    $item = Item::factory()->create(['type' => ItemType::ITEM, 'cost' => 5_000]);
    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $bank = Addrbook::create(['name' => 'BCA Test', 'type' => Addrbook::TYPE_BANK]);
    $gaji = Addrbook::create(['name' => 'Gaji Mingguan', 'type' => Addrbook::TYPE_ACCOUNT]);
    $material = Addrbook::create(['name' => 'Material Produksi', 'type' => Addrbook::TYPE_ACCOUNT]);

    ReportingLedgerRoleModel::create(['customer_id' => $gaji->id, 'role' => ReportingLedgerRole::ProductionCost]);
    ReportingLedgerRoleModel::create(['customer_id' => $material->id, 'role' => ReportingLedgerRole::Material]);

    createInventoryTransaction([
        'date' => '2026-01-10',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplier->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'total' => 50_000,
        'real_total' => 50_000,
    ], [
        'item_id' => $item->id,
        'quantity' => 10,
        'price' => 5_000,
        'total' => 50_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-12',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -30_000,
        'real_total' => -30_000,
    ], [
        'item_id' => $item->id,
        'quantity' => 3,
        'price' => 10_000,
        'total' => 30_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-20',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $gaji->id,
        'total' => -20_000,
        'real_total' => -20_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-22',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $material->id,
        'total' => -8_000,
        'real_total' => -8_000,
    ]);

    $january = $this->inventory->forMonth(2026, 1);

    expect($january['opening'])->toBe(100_000.0)
        ->and($january['material_purchases'])->toBe(50_000.0)
        ->and($january['cogs'])->toBe(15_000.0)
        ->and($january['production_cost'])->toBe(20_000.0)
        ->and($january['material_cash_out'])->toBe(8_000.0)
        ->and($january['closing'])->toBe(107_000.0);

    $persisted = ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 1)->first();
    expect($persisted)->not->toBeNull()
        ->and((float) $persisted->opening_balance)->toBe(100_000.0)
        ->and((float) $persisted->closing_balance)->toBe(107_000.0);
});

it('uses the previous month closing as the next opening', function () {
    seedPersediaanAwal(80_000);

    $item = Item::factory()->create(['type' => ItemType::ITEM, 'cost' => 2_000]);
    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();

    createInventoryTransaction([
        'date' => '2026-01-08',
        'type' => Transaction::TYPE_BUY,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'sender_id' => $supplier->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $warehouse->id,
        'total' => 20_000,
        'real_total' => 20_000,
    ], [
        'item_id' => $item->id,
        'quantity' => 10,
        'price' => 2_000,
        'total' => 20_000,
    ]);

    $this->inventory->ensureThrough(2026, 2);
    $february = $this->inventory->forMonth(2026, 2);

    expect($february['opening'])->toBe(100_000.0)
        ->and($february['closing'])->toBe(100_000.0);

    $jan = ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 1)->first();
    $feb = ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 2)->first();

    expect((float) $feb->opening_balance)->toBe((float) $jan->closing_balance);
});

it('attributes gaji mingguan cash-out to the paying bank entity', function () {
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal-inv', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'BCA Crystal Inv', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $gaji = Addrbook::create(['name' => 'Gaji Mingguan', 'type' => Addrbook::TYPE_ACCOUNT]);
    ReportingLedgerRoleModel::create(['customer_id' => $gaji->id, 'role' => ReportingLedgerRole::ProductionCost]);

    createInventoryTransaction([
        'date' => '2026-01-18',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $gaji->id,
        'total' => -12_500,
        'real_total' => -12_500,
    ]);

    $flows = $this->inventory->flowForMonth(2026, 1);

    expect($flows['production_cost'])->toBe(12_500.0)
        ->and($flows['production_cost_by_entity'][$entity->id])->toBe(12_500.0);
});

it('rebuilds the roll-forward from the artisan command', function () {
    seedPersediaanAwal(5_000);

    Artisan::call('reporting:rebuild-inventory', ['--from' => '2026-01', '--to' => '2026-01']);

    expect(ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 1)->exists())->toBeTrue()
        ->and((float) ReportingMonthlyInventoryValue::query()->where('year', 2026)->where('month', 1)->value('opening_balance'))
        ->toBe(5_000.0);
});

function seedManufacturedItem(?int $cost = 1_000): Item
{
    return Item::factory()->create(['type' => ItemType::ITEM, 'cost' => $cost]);
}

function seedGudangProduksi(Item $item, int $quantity, string $gudangDate): Produksi
{
    return Produksi::create([
        'temp_name' => $item->name,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'status' => Produksi::STATUS_GUDANG,
        'gudang_date' => $gudangDate,
    ]);
}

function seedBoronganForRange(User $user, string $from, string $to, float $total): Borongan
{
    $jahit = Worker::create(['name' => 'Jahit COGS '.$from, 'type' => Worker::TYPE_JAHIT]);

    return Borongan::create([
        'date' => $to,
        'user_id' => $user->id,
        'jahit_id' => $jahit->id,
        'from' => $from,
        'to' => $to,
        'total' => $total,
        'total_items' => 0,
    ]);
}

it('estimates manufactured cogs from gudang pcs, borongan, and material produksi', function () {
    seedPersediaanAwal(100_000);

    $item = seedManufacturedItem(9_999);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $bank = Addrbook::create(['name' => 'BCA Mfg', 'type' => Addrbook::TYPE_BANK]);
    $material = Addrbook::create(['name' => 'Material Produksi', 'type' => Addrbook::TYPE_ACCOUNT]);
    ReportingLedgerRoleModel::create(['customer_id' => $material->id, 'role' => ReportingLedgerRole::Material]);

    seedGudangProduksi($item, 100, '2026-01-15');
    seedBoronganForRange($this->user, '2026-01-01', '2026-01-31', 40_000);

    createInventoryTransaction([
        'date' => '2026-01-20',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $material->id,
        'total' => -10_000,
        'real_total' => -10_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-25',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -80_000,
        'real_total' => -80_000,
    ], [
        'item_id' => $item->id,
        'quantity' => 40,
        'price' => 2_000,
        'total' => 80_000,
    ]);

    $january = $this->inventory->forMonth(2026, 1);

    expect($january['pcs_manufactured'])->toBe(100.0)
        ->and($january['borongan_labor'])->toBe(40_000.0)
        ->and($january['material_cash_out'])->toBe(10_000.0)
        ->and($january['manufactured_unit_cost'])->toBe(500.0)
        ->and($january['manufactured_cogs'])->toBe(20_000.0)
        ->and($january['purchased_cogs'])->toBe(0.0)
        ->and($january['cogs'])->toBe(20_000.0)
        ->and($january['capitalize_conversion'])->toBeTrue()
        ->and($january['closing'])->toBe(130_000.0);
});

it('keeps item.cost cogs for purchased items and does not double-count gaji when borongan exists', function () {
    seedPersediaanAwal(50_000);

    $made = seedManufacturedItem(8_000);
    $bought = Item::factory()->create(['type' => ItemType::ITEM, 'cost' => 2_000]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $bank = Addrbook::create(['name' => 'BCA Mixed', 'type' => Addrbook::TYPE_BANK]);
    $gaji = Addrbook::create(['name' => 'Gaji Mingguan', 'type' => Addrbook::TYPE_ACCOUNT]);
    $material = Addrbook::create(['name' => 'Material Produksi', 'type' => Addrbook::TYPE_ACCOUNT]);
    ReportingLedgerRoleModel::create(['customer_id' => $gaji->id, 'role' => ReportingLedgerRole::ProductionCost]);
    ReportingLedgerRoleModel::create(['customer_id' => $material->id, 'role' => ReportingLedgerRole::Material]);

    seedGudangProduksi($made, 50, '2026-01-10');
    seedBoronganForRange($this->user, '2026-01-06', '2026-01-12', 25_000);

    createInventoryTransaction([
        'date' => '2026-01-18',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $gaji->id,
        'total' => -15_000,
        'real_total' => -15_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-19',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $material->id,
        'total' => -5_000,
        'real_total' => -5_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-22',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -30_000,
        'real_total' => -30_000,
    ], [
        'item_id' => $made->id,
        'quantity' => 10,
        'price' => 3_000,
        'total' => 30_000,
    ]);

    createInventoryTransaction([
        'date' => '2026-01-23',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -12_000,
        'real_total' => -12_000,
    ], [
        'item_id' => $bought->id,
        'quantity' => 3,
        'price' => 4_000,
        'total' => 12_000,
    ]);

    $january = $this->inventory->forMonth(2026, 1);

    expect($january['manufactured_unit_cost'])->toBe(600.0)
        ->and($january['manufactured_cogs'])->toBe(6_000.0)
        ->and($january['purchased_cogs'])->toBe(6_000.0)
        ->and($january['cogs'])->toBe(12_000.0)
        ->and($january['production_cost'])->toBe(15_000.0)
        ->and($january['labor_source'])->toBe('borongan')
        ->and($january['closing'])->toBe(68_000.0);
});

it('falls back to the prior month unit cost when no pcs entered gudang', function () {
    seedPersediaanAwal(0);

    $item = seedManufacturedItem(1);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    seedGudangProduksi($item, 80, '2026-01-12');
    seedBoronganForRange($this->user, '2026-01-01', '2026-01-31', 40_000);

    $this->inventory->forMonth(2026, 1);

    createInventoryTransaction([
        'date' => '2026-02-08',
        'type' => Transaction::TYPE_SELL,
        'sender_type' => Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'total' => -20_000,
        'real_total' => -20_000,
    ], [
        'item_id' => $item->id,
        'quantity' => 8,
        'price' => 2_500,
        'total' => 20_000,
    ]);

    $february = $this->inventory->forMonth(2026, 2);

    expect($february['pcs_manufactured'])->toBe(0.0)
        ->and($february['manufactured_unit_cost'])->toBe(500.0)
        ->and($february['unit_cost_source'])->toBe('prior')
        ->and($february['manufactured_cogs'])->toBe(4_000.0)
        ->and($february['closing'])->toBe(36_000.0);
});

it('counts only gudang produksi as manufactured pcs', function () {
    $item = seedManufacturedItem();

    Produksi::create([
        'temp_name' => 'Still cutting',
        'item_id' => $item->id,
        'quantity' => 40,
        'status' => Produksi::STATUS_PRODUKSI,
        'potong_date' => '2026-01-05',
    ]);
    seedGudangProduksi($item, 12, '2026-01-20');

    $flows = $this->inventory->flowForMonth(2026, 1);

    expect($flows['pcs_manufactured'])->toBe(12.0);
});

it('prorates a borongan week that spans two months', function () {
    seedBoronganForRange($this->user, '2026-01-27', '2026-02-02', 70_000);

    $january = $this->inventory->flowForMonth(2026, 1);
    $february = $this->inventory->flowForMonth(2026, 2);

    expect($january['borongan_labor'])->toBe(50_000.0)
        ->and($february['borongan_labor'])->toBe(20_000.0);
});
