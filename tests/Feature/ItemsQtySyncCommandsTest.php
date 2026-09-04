<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function seedQtySyncWarehouseStock(Item $item, Addrbook $warehouse, float $qty): WarehouseItem
{
    return WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) $warehouse->type,
        'quantity' => $qty,
    ]);
}

function seedQtySyncSellMovement(Addrbook $warehouse, Addrbook $customer, Item $item, float $qty): void
{
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'status' => Transaction::STATUS_COMPLETED,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) $customer->type,
        'user_id' => auth()->id() ?? User::factory()->create()->id,
        'total_items' => $qty,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'quantity' => $qty,
        'price' => 1000,
        'discount' => 0,
        'total' => $qty * 1000,
    ]);
}

it('inventory recalculate syncs items qty without modifying warehouse_item', function () {
    $physical = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['qty' => 999]);

    seedQtySyncWarehouseStock($item, $physical, 7);
    seedQtySyncSellMovement($physical, $customer, $item, 50);

    $this->artisan('inventory:recalculate')->assertSuccessful();

    expect((float) $item->fresh()->qty)->toBe(7.0)
        ->and((float) WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $physical->id)->value('quantity'))
        ->toBe(7.0);
});

it('report recalculate syncs items qty without modifying warehouse_item', function () {
    $physical = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['qty' => 0]);

    seedQtySyncWarehouseStock($item, $physical, 4);

    $this->artisan('report:recalculate')->assertSuccessful();

    expect((float) $item->fresh()->qty)->toBe(4.0)
        ->and((float) WarehouseItem::where('item_id', $item->id)->value('quantity'))->toBe(4.0);
});

it('backfill items qty matches inventory recalculate behavior', function () {
    $physical = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['qty' => 12]);

    seedQtySyncWarehouseStock($item, $physical, 9);

    $this->artisan('app:backfill-items-qty')->assertSuccessful();

    expect((float) $item->fresh()->qty)->toBe(9.0);
});

it('migrate finalize aggregation command was removed', function () {
    expect(collect(Artisan::all())->keys())->not->toContain('migrate:finalize-aggregation');
});

it('reset reports command was removed', function () {
    expect(collect(Artisan::all())->keys())->not->toContain('app:reset-reports');
});
