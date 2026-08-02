<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
});

test('deleting a buy transaction soft deletes it and redirects to the index', function () {
    // 1. Setup Data
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

    // 2. Create Transaction
    $response = $this->post(route('transactions.store'), [
        'date' => now()->toDateString(),
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

    // Check initial impact
    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(10.0);
    expect((float) $item->fresh()->qty)->toBe(10.0);

    // 3. Delete Transaction — destroy() soft deletes and redirects to the index
    $response = $this->delete(route('transactions.destroy', $transaction));
    $response->assertRedirect(route('transactions.index'));
    $response->assertSessionHas('success');

    $t = Transaction::withTrashed()->find($transaction->id);

    // Verify soft delete: the transaction is trashed and no longer visible
    // through the default (non-trashed) query.
    expect($t->deleted_at)->not->toBeNull();
    expect(Transaction::find($transaction->id))->toBeNull();
});
