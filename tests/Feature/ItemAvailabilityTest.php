<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\ItemAvailabilityService;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function seedItemAvailabilityStock(Item $item, Addrbook $warehouse, float $qty): WarehouseItem
{
    return WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) $warehouse->type,
        'quantity' => $qty,
    ]);
}

function seedItemAvailabilityMovement(
    int $type,
    Addrbook $sender,
    Addrbook $receiver,
    Item $item,
    float $qty,
    int $status = Transaction::STATUS_COMPLETED,
): Transaction {
    $transaction = Transaction::factory()->create([
        'type' => $type,
        'status' => $status,
        'sender_id' => $sender->id,
        'sender_type' => (string) $sender->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
        'user_id' => auth()->id() ?? User::factory()->create()->id,
        'total_items' => $qty,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => $type,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'quantity' => $qty,
        'price' => 1000,
        'discount' => 0,
        'total' => $qty * 1000,
    ]);

    return $transaction;
}

it('item show availability excludes virtual warehouse negatives', function () {
    $physical = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Fisik']);
    $virtual = Addrbook::factory()->create([
        'name' => 'V-WH Minus',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $item = Item::factory()->create(['name' => 'Avail SKU', 'qty' => 99]);

    seedItemAvailabilityStock($item, $physical, 12);
    seedItemAvailabilityStock($item, $virtual, -20);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="available-stock"', false)
        ->assertSee('data-testid="copy-item-availability-table"', false)
        ->assertSee('12 Units', false)
        ->assertSee('Gudang Fisik', false)
        ->assertSee('V-WH Minus', false)
        ->assertSee('Virtual Warehouses (-20 units, excluded from available)', false)
        ->assertSee('Stored qty 99', false)
        ->assertSee('data-testid="recalculate-qty"', false)
        ->assertDontSee('> -8 Units<', false);
});

it('assetlancar show availability excludes virtual warehouse stock', function () {
    $physical = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Aset']);
    $virtual = Addrbook::factory()->create([
        'name' => 'V-WH Aset',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'name' => 'Aset Avail',
        'qty' => 5,
    ]);

    seedItemAvailabilityStock($item, $physical, 8);
    seedItemAvailabilityStock($item, $virtual, -30);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('data-testid="copy-item-availability-table"', false)
        ->assertSee('8 Units', false)
        ->assertSee('Gudang Aset', false)
        ->assertSee('Virtual Warehouses (-30 units, excluded from available)', false)
        ->assertSee('data-testid="recalculate-qty"', false);
});

it('items index qty excludes virtual and deleted warehouses', function () {
    $physical = Addrbook::factory()->warehouse()->create();
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $deleted = Addrbook::factory()->warehouse()->create();
    $deleted->delete();

    $item = Item::factory()->create(['name' => 'Index Avail SKU', 'qty' => 40]);

    seedItemAvailabilityStock($item, $physical, 9);
    seedItemAvailabilityStock($item, $virtual, -15);
    seedItemAvailabilityStock($item, $deleted, 6);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('Index Avail SKU', false)
        ->assertSee('>9<', false)
        ->assertDontSee('>40<', false)
        ->assertDontSee('>-6<', false);
});

it('recalculates warehouse rows from transactions and writes physical qty', function () {
    $supplier = Addrbook::factory()->supplier()->create();
    $customer = Addrbook::factory()->customer()->create();
    $physical = Addrbook::factory()->warehouse()->create(['name' => 'WH Recalc']);
    $virtual = Addrbook::factory()->create([
        'name' => 'V-WH Recalc',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $item = Item::factory()->create(['qty' => 50]);

    seedItemAvailabilityStock($item, $physical, 1);
    seedItemAvailabilityStock($item, $virtual, -99);

    $this->actingAs($this->user);

    seedItemAvailabilityMovement(Transaction::TYPE_BUY, $supplier, $physical, $item, 10);
    seedItemAvailabilityMovement(Transaction::TYPE_SELL, $virtual, $customer, $item, 4);
    seedItemAvailabilityMovement(Transaction::TYPE_BUY, $supplier, $physical, $item, 3, Transaction::STATUS_CANCELLED);

    $this->post(route('items.recalculate-qty', $item))
        ->assertRedirect(route('items.show', $item));

    expect((float) $item->fresh()->qty)->toBe(10.0);
    expect((float) WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $physical->id)->value('quantity'))
        ->toBe(10.0);
    expect((float) WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $virtual->id)->value('quantity'))
        ->toBe(-4.0);

    $this->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Quantity recalculated', false)
        ->assertSee('10 Units', false)
        ->assertDontSee('Stored qty', false);
});

it('recalculates asset lancar qty from the assetlancar route', function () {
    $supplier = Addrbook::factory()->supplier()->create();
    $physical = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'qty' => 0,
    ]);

    seedItemAvailabilityStock($item, $physical, 2);
    $this->actingAs($this->user);
    seedItemAvailabilityMovement(Transaction::TYPE_BUY, $supplier, $physical, $item, 6);

    $this->post(route('assetlancar.recalculate-qty', $item))
        ->assertRedirect(route('assetlancar.show', $item));

    expect((float) $item->fresh()->qty)->toBe(6.0);
});

it('forbids recalculate qty without edit permission', function () {
    User::factory()->create();
    $viewer = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($viewer)
        ->post(route('items.recalculate-qty', $item))
        ->assertForbidden();
});

it('partitions warehouse items into physical virtual and deleted', function () {
    $physical = Addrbook::factory()->warehouse()->create();
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $deleted = Addrbook::factory()->warehouse()->create();
    $deleted->delete();

    $item = Item::factory()->create();
    seedItemAvailabilityStock($item, $physical, 5);
    seedItemAvailabilityStock($item, $virtual, -8);
    seedItemAvailabilityStock($item, $deleted, 3);

    $item->load(['warehouseItems.warehouse']);
    $stock = app(ItemAvailabilityService::class)->partitionWarehouseItems($item->warehouseItems);

    expect($stock['available'])->toBe(5.0)
        ->and($stock['virtual_stock'])->toBe(-8.0)
        ->and($stock['deleted_stock'])->toBe(3.0)
        ->and($stock['physical'])->toHaveCount(1)
        ->and($stock['virtual'])->toHaveCount(1)
        ->and($stock['deleted'])->toHaveCount(1);

    expect(app(ItemAvailabilityService::class)->availableQuantity($item->id))->toBe(5.0);
});
