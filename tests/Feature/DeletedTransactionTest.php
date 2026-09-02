<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\DeletedTransaction;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\WarehouseItemStatsRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
});

test('deleting a buy transaction reverts stock and balances then moves it to deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $supplier = Addrbook::create([
        'name' => 'Test Supplier',
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);

    $warehouse = Addrbook::create([
        'name' => 'Test Warehouse',
        'type' => Addrbook::TYPE_WAREHOUSE,
    ]);

    $item = Item::factory()->create([
        'qty' => 0,
    ]);

    $buyDate = now()->subDays(2)->toDateString();
    $laterDate = now()->subDay()->toDateString();

    $response = $this->post(route('transactions.store'), [
        'date' => $buyDate,
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 10,
                'price' => 5000,
            ],
        ],
    ]);

    $transaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $transaction));

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(10.0);
    expect((float) $item->fresh()->qty)->toBe(10.0);
    expect((float) $transaction->fresh()->sender_balance)->toBe(50000.0);

    $laterBuy = $this->post(route('transactions.store'), [
        'date' => $laterDate,
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 5,
                'price' => 2000,
            ],
        ],
    ]);

    $laterTransaction = Transaction::latest('id')->first();
    $laterBuy->assertRedirect(route('transactions.show', $laterTransaction));
    expect((float) $laterTransaction->fresh()->sender_balance)->toBe(60000.0);

    $response = $this->delete(route('transactions.destroy', $transaction));
    $response->assertRedirect(route('transactions.index'));
    $response->assertSessionHas('success');

    expect(Transaction::find($transaction->id))->toBeNull();

    $deleted = DeletedTransaction::find($transaction->id);
    expect($deleted)->not->toBeNull();
    expect((float) $deleted->sender_balance)->toBe(50000.0);
    expect((float) $deleted->receiver_balance)->toBe(0.0);

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(5.0);
    expect((float) $item->fresh()->qty)->toBe(5.0);
    expect((float) $laterTransaction->fresh()->sender_balance)->toBe(10000.0);
    expect((float) AddrbookStat::where('customer_id', $supplier->id)->first()->balance)->toBe(10000.0);
});

test('deleting a sell transaction adds each sold qty back to the sender warehouse', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $shirt = Item::factory()->create(['qty' => 20, 'price' => 100_000, 'cost' => 40_000]);
    $pants = Item::factory()->create(['qty' => 15, 'price' => 200_000, 'cost' => 80_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $shirt->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 20,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $pants->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 15,
    ]);

    $this->post(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'discount_percent' => 100,
        'items' => [
            ['item_id' => $shirt->id, 'quantity' => 3, 'price' => 100_000, 'discount' => 0],
            ['item_id' => $pants->id, 'quantity' => 4, 'price' => 200_000, 'discount' => 0],
        ],
    ])->assertRedirect();

    $sell = Transaction::query()->where('type', Transaction::TYPE_SELL)->latest('id')->first();
    expect($sell)->not->toBeNull()
        ->and((float) $sell->total)->toBe(0.0)
        ->and((float) $sell->receiver_balance)->toBe(0.0)
        ->and((float) (AddrbookStat::where('customer_id', $customer->id)->value('balance') ?? 0))->toBe(0.0);

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $shirt->id)->value('quantity'))->toBe(17.0)
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $pants->id)->value('quantity'))->toBe(11.0)
        ->and((float) $shirt->fresh()->qty)->toBe(17.0)
        ->and((float) $pants->fresh()->qty)->toBe(11.0);

    $sell->load('details');
    $recorder = app(WarehouseItemStatsRecorder::class);
    foreach ($sell->details as $detail) {
        $recorder->recordDetail($sell, $detail);
    }

    expect((float) WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $shirt->id)
        ->value('sold_qty'))->toBe(3.0)
        ->and((float) WarehouseItemMonthlyStat::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $pants->id)
            ->value('sold_qty'))->toBe(4.0);

    $this->delete(route('transactions.destroy', $sell))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect(Transaction::find($sell->id))->toBeNull();
    expect(DeletedTransaction::find($sell->id))->not->toBeNull();

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $shirt->id)->value('quantity'))->toBe(20.0)
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $pants->id)->value('quantity'))->toBe(15.0)
        ->and((float) $shirt->fresh()->qty)->toBe(20.0)
        ->and((float) $pants->fresh()->qty)->toBe(15.0)
        ->and((float) WarehouseItem::where('warehouse_id', $customer->id)->where('item_id', $shirt->id)->value('quantity'))->toBe(0.0)
        ->and((float) WarehouseItem::where('warehouse_id', $customer->id)->where('item_id', $pants->id)->value('quantity'))->toBe(0.0)
        ->and((float) WarehouseItemMonthlyStat::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $shirt->id)
            ->value('sold_qty'))->toBe(0.0)
        ->and((float) WarehouseItemMonthlyStat::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $pants->id)
            ->value('sold_qty'))->toBe(0.0)
        ->and((float) (AddrbookStat::where('customer_id', $customer->id)->value('balance') ?? 0))->toBe(0.0);
});

test('deleting a swapped 100 percent sell restores warehouse qty and receiver balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['qty' => 10, 'price' => 1_591_000, 'cost' => 800_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 10,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => now()->toDateString(),
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'discount' => 100,
        'total' => -1_591_000,
        'real_total' => 0,
        'ppn' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $user->id,
    ]);
    $sell->details()->create([
        'item_id' => $item->id,
        'date' => $sell->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'quantity' => 1,
        'price' => 1_591_000,
        'discount' => 0,
        'total' => 1_591_000,
    ]);

    app(\App\Services\TransactionService::class)->handleTransaction($sell->fresh('details'));

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))->toBe(9.0)
        ->and((float) $sell->fresh()->receiver_balance)->toBe(-1_591_000.0)
        ->and((float) AddrbookStat::where('customer_id', $customer->id)->value('balance'))->toBe(-1_591_000.0);

    $this->delete(route('transactions.destroy', $sell))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect(Transaction::find($sell->id))->toBeNull()
        ->and((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))->toBe(10.0)
        ->and((float) $item->fresh()->qty)->toBe(10.0)
        ->and((float) (AddrbookStat::where('customer_id', $customer->id)->value('balance') ?? 0))->toBe(0.0);
});

test('deleting a transaction with legacy invalid due date still archives to deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $bankSource = Addrbook::create([
        'name' => 'Legacy Due Source',
        'type' => Addrbook::TYPE_BANK,
    ]);

    $bankDest = Addrbook::create([
        'name' => 'Legacy Due Dest',
        'type' => Addrbook::TYPE_BANK,
    ]);

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 15000000,
        'invoice' => (string) random_int(100000, 999999),
        'description' => '',
    ])->assertRedirect();

    $transaction = Transaction::latest('id')->first();

    DB::table('transactions')->where('id', $transaction->id)->update(['due' => '0000-00-00']);

    $response = $this->delete(route('transactions.destroy', $transaction));
    $response->assertRedirect(route('transactions.index'));
    $response->assertSessionHas('success');

    expect(Transaction::find($transaction->id))->toBeNull();

    $deleted = DeletedTransaction::find($transaction->id);
    expect($deleted)->not->toBeNull();
    expect($deleted->due?->format('Y-m-d'))->toBe('1970-01-01');
});

test('deleted transaction show page renders clickable item and party links', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $supplier = Addrbook::create([
        'name' => 'Link Supplier',
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);

    $warehouse = Addrbook::create([
        'name' => 'Link Warehouse',
        'type' => Addrbook::TYPE_WAREHOUSE,
    ]);

    $item = Item::factory()->create(['name' => 'Deleted Show Item']);

    $this->post(route('transactions.store'), [
        'date' => now()->format('Y-m-d'),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 1000,
            ],
        ],
    ])->assertRedirect();

    $transaction = Transaction::latest('id')->first();

    $this->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->get(route('transactions.deleted.show', $transaction->id))
        ->assertOk()
        ->assertSee(route('items.show', $item->id), false)
        ->assertSee(route('addrbook.type.show', ['type' => $supplier->type_slug, 'addrbook' => $supplier->id]), false)
        ->assertSee(route('addrbook.type.show', ['type' => $warehouse->type_slug, 'addrbook' => $warehouse->id]), false);
});
