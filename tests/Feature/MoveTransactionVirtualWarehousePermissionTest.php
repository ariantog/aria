<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    Permission::firstOrCreate(['name' => 'transactions-type-move']);
    Permission::firstOrCreate(['name' => 'transactions-type-move-virtual']);

    $this->user = User::factory()->create();
    expect($this->user->id)->not->toBe(User::SUPERADMIN_ID);
    $this->user->givePermissionTo('transactions-type-move');
    $this->actingAs($this->user);
});

function movePermPhysicalWarehouse(?string $name = null): Addrbook
{
    return Addrbook::factory()->create([
        'name' => $name ?? 'Gudang Fisik',
        'type' => Addrbook::TYPE_WAREHOUSE,
    ]);
}

function movePermVirtualWarehouse(?string $name = null): Addrbook
{
    return Addrbook::factory()->create([
        'name' => $name ?? 'Gudang Virtual',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
}

function movePermPost(Addrbook $sender, Addrbook $receiver, Item $item, float $qty = 1): \Illuminate\Testing\TestResponse
{
    return test()->post(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'move',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => $qty,
                'price' => 1000,
                'discount' => 0,
            ],
        ],
    ]);
}

it('restricts move parties to physical warehouses without virtual move permission', function () {
    $physical = movePermPhysicalWarehouse();
    $virtual = movePermVirtualWarehouse();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $physical->id,
        'item_id' => $item->id,
        'warehouse_type' => $physical->type,
        'quantity' => 5,
    ]);

    movePermPost($virtual, $physical, $item)->assertSessionHasErrors('sender_id');
    movePermPost($physical, $virtual, $item)->assertSessionHasErrors('receiver_id');

    expect(Transaction::query()->where('type', Transaction::TYPE_MOVE)->count())->toBe(0);
});

it('allows warehouse to warehouse move with only type-move permission', function () {
    $source = movePermPhysicalWarehouse('Asal');
    $dest = movePermPhysicalWarehouse('Tujuan');
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $source->id,
        'item_id' => $item->id,
        'warehouse_type' => $source->type,
        'quantity' => 3,
    ]);

    movePermPost($source, $dest, $item, 2)->assertRedirect();

    expect(Transaction::query()->where('type', Transaction::TYPE_MOVE)->count())->toBe(1)
        ->and((float) WarehouseItem::where('warehouse_id', $source->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(1.0)
        ->and((float) WarehouseItem::where('warehouse_id', $dest->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(2.0);
});

it('rejects move that would take a physical source warehouse negative', function () {
    $source = movePermPhysicalWarehouse();
    $dest = movePermPhysicalWarehouse('Tujuan');
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $source->id,
        'item_id' => $item->id,
        'warehouse_type' => $source->type,
        'quantity' => 1,
    ]);

    movePermPost($source, $dest, $item, 4)->assertSessionHasErrors('items');

    expect((float) WarehouseItem::where('warehouse_id', $source->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(1.0);
});

it('allows virtual warehouse parties when user has move-virtual permission', function () {
    $this->user->givePermissionTo('transactions-type-move-virtual');

    $virtual = movePermVirtualWarehouse();
    $physical = movePermPhysicalWarehouse();
    $item = Item::factory()->create();

    movePermPost($virtual, $physical, $item, 2)->assertRedirect();

    expect((float) WarehouseItem::where('warehouse_id', $virtual->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(-2.0)
        ->and((float) WarehouseItem::where('warehouse_id', $physical->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(2.0);
});

it('exposes virtual warehouses in move lookup only with virtual permission', function () {
    $virtual = movePermVirtualWarehouse('V Lookup Test');

    $this->getJson(route('transactions.lookup', [
        'type' => 'move',
        'role' => 'sender',
        'addrbook_type' => Addrbook::TYPE_WAREHOUSE.','.Addrbook::TYPE_V_WAREHOUSE,
        'search' => 'Lookup',
    ]))
        ->assertOk()
        ->assertJsonCount(0);

    $this->user->givePermissionTo('transactions-type-move-virtual');

    $this->getJson(route('transactions.lookup', [
        'type' => 'move',
        'role' => 'sender',
        'addrbook_type' => Addrbook::TYPE_WAREHOUSE.','.Addrbook::TYPE_V_WAREHOUSE,
        'search' => 'Lookup',
    ]))
        ->assertOk()
        ->assertJsonFragment(['id' => $virtual->id]);
});
