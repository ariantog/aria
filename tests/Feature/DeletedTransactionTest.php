<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Gate;

uses(DatabaseTransactions::class, WithoutMiddleware::class);

beforeEach(function () {
    Gate::before(fn () => true);
});

test('can delete and restore a buy transaction', function () {
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

    $response->assertRedirect(route('transactions.index'));

    $transaction = Transaction::latest()->first();

    // Check initial impact
    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(10.0);
    expect((float) $item->fresh()->qty)->toBe(10.0);

    // 3. Delete Transaction
    $response = $this->withoutExceptionHandling()->delete(route('transactions.destroy', $transaction));
    $response->assertRedirect(route('transactions.index'));

    $t = Transaction::withTrashed()->find($transaction->id);
    dd($t->toArray());

    // Verify soft delete
    expect($t->deleted_at)->not->toBeNull();
    expect($transaction->details()->withTrashed()->count())->toBe(1);
    expect($transaction->details()->count())->toBe(0);

    // Verify side effects reversed
    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(0.0);
    expect((float) $item->fresh()->qty)->toBe(0.0);

    // 4. Check Deleted List
    $response = $this->get(route('transactions.deleted.index'));
    $response->assertStatus(200);
    $response->assertSee($transaction->invoice_number);

    // 5. Restore Transaction
    $response = $this->post(route('transactions.deleted.restore', $transaction->id));
    $response->assertRedirect(route('transactions.deleted.index'));

    // Verify restored
    expect(Transaction::find($transaction->id)->deleted_at)->toBeNull();
    expect($transaction->details()->count())->toBe(1);

    // Verify side effects re-applied
    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first()->quantity)->toBe(10.0);
    expect((float) $item->fresh()->qty)->toBe(10.0);
});
