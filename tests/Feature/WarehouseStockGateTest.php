<?php

use App\Exceptions\InsufficientWarehouseStockException;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function stockGateWarehouse(?string $name = null, int $type = Addrbook::TYPE_WAREHOUSE): Addrbook
{
    return Addrbook::factory()->create([
        'name' => $name ?? 'Gudang Gate',
        'type' => $type,
    ]);
}

function stockGatePostItemTransaction(string $type, Addrbook $sender, Addrbook $receiver, Item $item, float $qty, float $price = 1000): \Illuminate\Testing\TestResponse
{
    return test()->post(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => $type,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => $qty,
                'price' => $price,
                'discount' => 0,
            ],
        ],
    ]);
}

function stockGateHandle(int $type, Addrbook $sender, Addrbook $receiver, Item $item, float $qty, float $price = 1000): Transaction
{
    $transaction = Transaction::factory()->create([
        'type' => $type,
        'date' => now()->toDateString(),
        'sender_id' => $sender->id,
        'sender_type' => $sender->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => $receiver->type,
        'status' => Transaction::STATUS_COMPLETED,
        'total' => $qty * $price,
        'real_total' => $qty * $price,
        'user_id' => auth()->id(),
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $transaction->date,
        'transaction_type' => $type,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'quantity' => $qty,
        'price' => $price,
        'discount' => 0,
        'total' => $qty * $price,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    return $transaction->fresh('details');
}

it('rejects a sell that would take a physical warehouse negative', function () {
    $warehouse = stockGateWarehouse('Gudang Jual');
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'SELL-NEG', 'name' => 'Sell Neg Item']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 2,
    ]);

    stockGatePostItemTransaction('sell', $warehouse, $customer, $item, 5)
        ->assertSessionHasErrors('items');

    expect(Transaction::query()->where('type', Transaction::TYPE_SELL)->count())->toBe(0)
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(2.0);
});

it('rejects a move that would take the source warehouse negative', function () {
    $source = stockGateWarehouse('Gudang Asal');
    $dest = stockGateWarehouse('Gudang Tujuan');
    $item = Item::factory()->create(['name' => 'Move Neg Item']);

    WarehouseItem::create([
        'warehouse_id' => $source->id,
        'item_id' => $item->id,
        'warehouse_type' => $source->type,
        'quantity' => 1,
    ]);

    stockGatePostItemTransaction('move', $source, $dest, $item, 4)
        ->assertSessionHasErrors('items');

    expect(Transaction::query()->where('type', Transaction::TYPE_MOVE)->count())->toBe(0)
        ->and((float) WarehouseItem::where('warehouse_id', $source->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(1.0)
        ->and(WarehouseItem::where('warehouse_id', $dest->id)->where('item_id', $item->id)->exists())->toBeFalse();
});

it('rejects return-supplier when the warehouse does not have the qty', function () {
    $warehouse = stockGateWarehouse('Gudang Retur Supplier');
    $supplier = Addrbook::factory()->supplier()->create();
    $item = Item::factory()->create(['name' => 'RS Neg Item']);

    stockGatePostItemTransaction('return-supplier', $warehouse, $supplier, $item, 3)
        ->assertSessionHasErrors('items');

    expect(Transaction::query()->where('type', Transaction::TYPE_RETURN_SUPPLIER)->count())->toBe(0);
});

it('still lets a buy post a negative qty on the supplier, not the warehouse', function () {
    $warehouse = stockGateWarehouse('Gudang Beli');
    $supplier = Addrbook::factory()->supplier()->create();
    $item = Item::factory()->create();

    stockGatePostItemTransaction('buy', $supplier, $warehouse, $item, 10, 500)
        ->assertRedirect();

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(10.0)
        ->and((float) WarehouseItem::where('warehouse_id', $supplier->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(-10.0);
});

it('blocks handleTransaction sell when CreateItemTransaction validation is skipped', function () {
    $warehouse = stockGateWarehouse();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'DIRECT-SELL']);

    expect(fn () => stockGateHandle(Transaction::TYPE_SELL, $warehouse, $customer, $item, 3))
        ->toThrow(InsufficientWarehouseStockException::class, 'DIRECT-SELL cuma ada 0, mau diambil 3');

    expect((float) (WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity') ?? 0))
        ->toBe(0.0);
});

it('blocks handleTransaction move when the source warehouse is short', function () {
    $source = stockGateWarehouse('Src');
    $dest = stockGateWarehouse('Dst');
    $item = Item::factory()->create(['code' => 'DIRECT-MOVE']);

    WarehouseItem::create([
        'warehouse_id' => $source->id,
        'item_id' => $item->id,
        'warehouse_type' => $source->type,
        'quantity' => 2,
    ]);

    expect(fn () => stockGateHandle(Transaction::TYPE_MOVE, $source, $dest, $item, 5))
        ->toThrow(InsufficientWarehouseStockException::class, 'DIRECT-MOVE cuma ada 2, mau diambil 5');

    expect((float) WarehouseItem::where('warehouse_id', $source->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(2.0);
});

it('allows handleTransaction sell from a virtual warehouse to go negative', function () {
    $virtual = stockGateWarehouse('V Gudang', Addrbook::TYPE_V_WAREHOUSE);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    stockGateHandle(Transaction::TYPE_SELL, $virtual, $customer, $item, 4);

    expect((float) WarehouseItem::where('warehouse_id', $virtual->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(-4.0);
});

it('refuses to delete a buy after the stock has already been sold', function () {
    $warehouse = stockGateWarehouse();
    $supplier = Addrbook::factory()->supplier()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'DEL-BUY']);

    stockGatePostItemTransaction('buy', $supplier, $warehouse, $item, 5, 1000)->assertRedirect();
    $buy = Transaction::query()->where('type', Transaction::TYPE_BUY)->first();

    stockGatePostItemTransaction('sell', $warehouse, $customer, $item, 5, 2000)->assertRedirect();

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(0.0);

    test()->delete(route('transactions.destroy', $buy))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Transaction::query()->find($buy->id))->not->toBeNull()
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(0.0);
});

it('rejects a jubelio cron sell when the mapped physical warehouse is short', function () {
    $warehouse = stockGateWarehouse('Gudang Cron');
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'JB-CRON']);

    Jubeliosync::create([
        'jubelio_store_id' => 11,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 22,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('cron-short-1', [
        'salesorder_no' => 'INV-CRON-SHORT',
        'store_id' => 11,
        'location_id' => 22,
        'sub_total' => 5000,
        'real_total' => 5000,
        'items' => [
            ['item_code' => 'JB-CRON', 'qty' => 2, 'price' => 2500],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'cron-short-1',
        'source' => 1,
        'invoice' => 'INV-CRON-SHORT',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Gudang Cron')
        ->and(Transaction::where('invoice', 'INV-CRON-SHORT')->exists())->toBeFalse();
});

it('rejects a jubelio manual create when the mapped physical warehouse is short', function () {
    $warehouse = stockGateWarehouse('Gudang Manual');
    $customer = Addrbook::factory()->customer()->create();
    Item::factory()->create(['code' => 'JB-MANUAL']);

    Jubeliosync::create([
        'jubelio_store_id' => 13,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 23,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('manual-short-1', [
        'salesorder_no' => 'INV-MANUAL-SHORT',
        'store_id' => 13,
        'location_id' => 23,
        'sub_total' => 9000,
        'real_total' => 9000,
        'items' => [
            ['item_code' => 'JB-MANUAL', 'qty' => 1, 'price' => 9000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'manual-short-1',
        'source' => 1,
        'invoice' => 'INV-MANUAL-SHORT',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order, $this->user->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Stok tidak cukup')
        ->and(Transaction::where('invoice', 'INV-MANUAL-SHORT')->exists())->toBeFalse();
});

it('lets a jubelio sell from a virtual warehouse go negative', function () {
    $virtual = stockGateWarehouse('V Jubelio', Addrbook::TYPE_V_WAREHOUSE);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'JB-VWH']);

    Jubeliosync::create([
        'jubelio_store_id' => 31,
        'jubelio_store_name' => 'Store V',
        'jubelio_location_id' => 32,
        'jubelio_location_name' => 'Loc V',
        'warehouse_id' => $virtual->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('vwh-sell-1', [
        'salesorder_no' => 'INV-VWH-SELL',
        'store_id' => 31,
        'location_id' => 32,
        'sub_total' => 4000,
        'real_total' => 4000,
        'items' => [
            ['item_code' => 'JB-VWH', 'qty' => 3, 'price' => 4000 / 3],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'vwh-sell-1',
        'source' => 1,
        'invoice' => 'INV-VWH-SELL',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeTrue()
        ->and((float) WarehouseItem::where('warehouse_id', $virtual->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(-3.0);
});
