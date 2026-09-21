<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->user = User::factory()->create();
});

it('stores a sell transaction with many line items via json', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $items = Item::factory()->count(10)->create();

    foreach ($items as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => $warehouse->type,
            'quantity' => 1000,
        ]);
    }

    $payloadItems = [];
    foreach ($items as $item) {
        for ($i = 0; $i < 91; $i++) {
            $payloadItems[] = [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 1000,
                'discount' => 0,
                'note' => '',
            ];
        }
    }

    expect(count($payloadItems))->toBe(910);

    $response = $this->actingAs($this->user)->postJson(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => $payloadItems,
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $transaction = Transaction::query()->where('type', Transaction::TYPE_SELL)->latest('id')->first();
    $response->assertCreated()
        ->assertJsonPath('redirect', route('transactions.show', $transaction, absolute: false));

    expect($transaction)->not->toBeNull()
        ->and($transaction->details()->count())->toBe(910);
});
