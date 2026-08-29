<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();

    $this->supplier = Addrbook::factory()->supplier()->create(['name' => 'PT Supplier Asset']);
    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Asset']);
    $this->expense = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT, 'name' => 'Beban Penyusutan']);
    $this->contra = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT, 'name' => 'Akumulasi Penyusutan']);
});

it('lists and creates an asset tetap register', function () {
    $this->actingAs($this->user)
        ->get(route('assettetap.index'))
        ->assertOk()
        ->assertSee('Asset Tetap', false)
        ->assertSee('Add Asset', false);

    $this->actingAs($this->user)
        ->post(route('assettetap.store'), [
            'name' => 'Mesin Jahit Juki',
            'useful_life_months' => 36,
            'residual_value' => 100000,
            'warehouse_id' => $this->warehouse->id,
        ])
        ->assertRedirect();

    $item = Item::query()->where('type', ItemType::ASSET_TETAP)->first();
    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Mesin Jahit Juki')
        ->and($item->code)->toStartWith('AT-')
        ->and($item->depreciation)->not->toBeNull()
        ->and((int) $item->depreciation->useful_life_months)->toBe(36)
        ->and((float) $item->depreciation->residual_value)->toBe(100000.0);

    $this->actingAs($this->user)
        ->get(route('assettetap.show', $item))
        ->assertOk()
        ->assertSee('Mesin Jahit Juki', false)
        ->assertSee('Record Buy', false);
});

it('records a buy transaction and keeps the asset in warehouse stock', function () {
    $this->actingAs($this->user)
        ->post(route('assettetap.store'), [
            'name' => 'Kendaraan Operasional',
            'useful_life_months' => 48,
            'residual_value' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

    $item = Item::query()->where('name', 'Kendaraan Operasional')->first();

    $this->actingAs($this->user)
        ->post(route('assettetap.buy.store', $item), [
            'date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'buy_price' => 120000000,
            'invoice' => 'AT-BUY-1',
        ])
        ->assertRedirect(route('assettetap.show', $item));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'AT-BUY-1',
        'sender_id' => $this->supplier->id,
        'receiver_id' => $this->warehouse->id,
        'total' => 120000000,
    ]);

    $item->refresh();
    expect($item->depreciation->hasBuyTransaction())->toBeTrue()
        ->and((float) $item->depreciation->buy_price)->toBe(120000000.0)
        ->and((float) $item->cost)->toBe(120000000.0)
        ->and((float) $item->qty)->toBe(1.0);

    $this->assertDatabaseHas('warehouse_item', [
        'warehouse_id' => $this->warehouse->id,
        'item_id' => $item->id,
        'quantity' => 1,
    ]);
});

it('posts monthly depreciation without changing warehouse stock and reports net book value', function () {
    $this->actingAs($this->user)
        ->post(route('assettetap.store'), [
            'name' => 'Laptop Kantor',
            'useful_life_months' => 4,
            'residual_value' => 0,
        ]);

    $item = Item::query()->where('name', 'Laptop Kantor')->first();

    $this->actingAs($this->user)
        ->post(route('assettetap.buy.store', $item), [
            'date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'buy_price' => 12000000,
        ]);

    $stockBefore = (float) WarehouseItem::query()
        ->where('warehouse_id', $this->warehouse->id)
        ->where('item_id', $item->id)
        ->value('quantity');

    $this->actingAs($this->user)
        ->get(route('assettetap.depreciate', [
            'month' => now()->month,
            'year' => now()->year,
        ]))
        ->assertOk()
        ->assertSee('Laptop Kantor', false);

    $this->actingAs($this->user)
        ->post(route('assettetap.depreciate.store'), [
            'month' => now()->month,
            'year' => now()->year,
            'expense_account_id' => $this->expense->id,
            'contra_account_id' => $this->contra->id,
        ])
        ->assertRedirect();

    $dep = Transaction::query()->where('type', Transaction::TYPE_DEPRECIATION)->first();
    expect($dep)->not->toBeNull()
        ->and($dep->invoice)->toBe('DEP-'.now()->format('Y-m'))
        ->and((float) $dep->total)->toBe(3000000.0)
        ->and($dep->details)->toHaveCount(1)
        ->and((int) $dep->details->first()->item_id)->toBe($item->id);

    $stockAfter = (float) WarehouseItem::query()
        ->where('warehouse_id', $this->warehouse->id)
        ->where('item_id', $item->id)
        ->value('quantity');

    expect($stockAfter)->toBe($stockBefore);

    $this->actingAs($this->user)
        ->get(route('assettetap.show', $item))
        ->assertOk()
        ->assertSee('9,000,000', false);

    $this->actingAs($this->user)
        ->get(route('reports.asset-tetap', [
            'month' => now()->month,
            'year' => now()->year,
        ]))
        ->assertOk()
        ->assertSee('Laptop Kantor', false)
        ->assertSee('9,000,000', false);

    $this->actingAs($this->user)
        ->post(route('assettetap.depreciate.store'), [
            'month' => now()->month,
            'year' => now()->year,
            'expense_account_id' => $this->expense->id,
            'contra_account_id' => $this->contra->id,
        ])
        ->assertRedirect();

    expect(Transaction::query()->where('type', Transaction::TYPE_DEPRECIATION)->count())->toBe(1);
});

it('skips fully depreciated assets on later months', function () {
    $this->actingAs($this->user)
        ->post(route('assettetap.store'), [
            'name' => 'Meja Kayu',
            'useful_life_months' => 1,
            'residual_value' => 0,
        ]);

    $item = Item::query()->where('name', 'Meja Kayu')->first();

    $this->actingAs($this->user)
        ->post(route('assettetap.buy.store', $item), [
            'date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'buy_price' => 500000,
        ]);

    $this->actingAs($this->user)
        ->post(route('assettetap.depreciate.store'), [
            'month' => now()->month,
            'year' => now()->year,
            'expense_account_id' => $this->expense->id,
            'contra_account_id' => $this->contra->id,
        ]);

    $next = now()->addMonthNoOverflow();
    $this->travelTo($next->copy()->endOfMonth());

    $this->actingAs($this->user)
        ->post(route('assettetap.depreciate.store'), [
            'month' => $next->month,
            'year' => $next->year,
            'expense_account_id' => $this->expense->id,
            'contra_account_id' => $this->contra->id,
        ]);

    expect(Transaction::query()->where('type', Transaction::TYPE_DEPRECIATION)->count())->toBe(1);
});

it('posts the previous month from the artisan command', function () {
    $this->travelTo(now()->startOfMonth()->addDays(5));

    $this->actingAs($this->user)
        ->post(route('assettetap.store'), [
            'name' => 'Printer Kantor',
            'useful_life_months' => 10,
            'residual_value' => 0,
        ]);

    $item = Item::query()->where('name', 'Printer Kantor')->first();

    $this->actingAs($this->user)
        ->post(route('assettetap.buy.store', $item), [
            'date' => now()->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'buy_price' => 10000000,
        ]);

    $this->artisan('app:run-monthly-depreciation', [
        '--month' => now()->copy()->subMonthNoOverflow()->format('Y-m'),
        '--expense' => $this->expense->id,
        '--contra' => $this->contra->id,
    ])->assertSuccessful();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_DEPRECIATION,
        'invoice' => 'DEP-'.now()->copy()->subMonthNoOverflow()->format('Y-m'),
        'total' => 1000000,
    ]);
});

it('forbids the register for users without assetTetap-list', function () {
    User::factory()->create();
    $viewer = User::factory()->create();
    expect($viewer->is_superadmin)->toBeFalse();

    $this->actingAs($viewer)
        ->get(route('assettetap.index'))
        ->assertForbidden();
});

it('allows a permitted non-superadmin to view the register', function () {
    User::factory()->create();
    $viewer = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Item');
    Permission::firstOrCreate(['name' => 'assetTetap-list', 'guard_name' => 'web']);
    $viewer->givePermissionTo('assetTetap-list');

    $this->actingAs($viewer)
        ->get(route('assettetap.index'))
        ->assertOk();
});
