<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\DeletedTransaction;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
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
    expect((float) AddrbookStat::where('addrbook_id', $supplier->id)->first()->balance)->toBe(10000.0);
});
