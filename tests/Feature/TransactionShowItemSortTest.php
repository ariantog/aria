<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders transaction item rows sorted by sku by default', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $late = Item::factory()->create(['name' => 'Zebra Shirt', 'code' => 'ZEBRA-99']);
    $early = Item::factory()->create(['name' => 'Alpha Shirt', 'code' => 'ALPHA-01']);
    $mid = Item::factory()->create(['name' => 'Mid Shirt', 'code' => 'MID-10']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SKU-SORT',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -30_000,
        'real_total' => -30_000,
        'total_items' => 3,
        'user_id' => $this->user->id,
    ]);

    foreach ([$late, $early, $mid] as $item) {
        TransactionDetail::factory()->create([
            'transaction_id' => $sell->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 10_000,
            'total' => 10_000,
        ]);
    }

    $html = $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('data-testid="tx-item-row"', false)
        ->assertSee("sortCol: 'sku'", false)
        ->getContent();

    preg_match_all('/data-testid="tx-item-row" data-sku="([^"]*)"/', $html, $matches);

    expect($matches[1])->toBe(['ALPHA-01', 'MID-10', 'ZEBRA-99']);
});

it('keeps the transaction show page sortable when there are no item rows', function () {
    $transaction = Transaction::factory()->create([
        'invoice' => 'INV-NO-ITEMS',
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertDontSee('data-testid="tx-item-row"', false)
        ->assertSee('sortCol: null', false);
});
