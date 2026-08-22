<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

it('renders addrbook item sales with sell lines for the contact', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['code' => 'SKU-CUST-1']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Item Sales']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Customer Item Sales']);

    $visibleTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-CUST-VISIBLE',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-CUST-HIDDEN',
        'sender_id' => $warehouse->id,
        'receiver_id' => Addrbook::factory()->customer()->create()->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'item_id' => $item->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'date' => now()->toDateString(),
        'quantity' => 2,
        'discount' => 5,
        'total' => 190_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
    ]);

    $this->actingAs($user)
        ->get(route('addrbook.type.item-sales', ['customer', $customer->id]))
        ->assertOk()
        ->assertSee('Item Sales', false)
        ->assertSee('SELL-CUST-VISIBLE', false)
        ->assertSee('SKU-CUST-1', false)
        ->assertSee('WH Item Sales', false)
        ->assertSee('Customer Item Sales', false)
        ->assertDontSee('SELL-CUST-HIDDEN', false);
});

it('does not leak the route type segment into sell line filters', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Route Leak']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Customer Route Leak']);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-ROUTE-LEAK',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('addrbook.type.item-sales', ['customer', $customer->id]))
        ->assertOk()
        ->assertSee('SELL-ROUTE-LEAK', false);
});

it('exports addrbook item sales to excel', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-CUST-XLS',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $response = $this->actingAs($user)->get(route('addrbook.type.item-sales.export', [
        'customer',
        $customer->id,
        'invoice' => 'SELL-CUST-XLS',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
