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

it('renders sku copy value on item rows for clipboard export', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['name' => 'Copy Shirt', 'code' => 'ACCHJ0000601M']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SKU-COPY',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -10_000,
        'real_total' => -10_000,
        'total_items' => 1,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 10_000,
        'total' => 10_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee(':data-copy-value="showLegacyCode ?', false)
        ->assertSee('ACCHJ0000601M', false)
        ->assertDontSee('x-show="showLegacyCode" x-cloak', false);
});

it('renders clickable sku links on transaction item rows', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['name' => 'Linked Shirt', 'code' => 'LINK-SKU-01']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SKU-LINK',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -10_000,
        'real_total' => -10_000,
        'total_items' => 1,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 10_000,
        'total' => 10_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('data-testid="tx-item-sku-link-'.$item->id.'"', false)
        ->assertSee(route('items.show', $item->id), false)
        ->assertSee('LINK-SKU-01', false);
});

it('displays total item quantity from line details on transaction show', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $itemA = Item::factory()->create(['code' => 'QTY-A']);
    $itemB = Item::factory()->create(['code' => 'QTY-B']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-TOTAL-QTY',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -50_000,
        'real_total' => -50_000,
        'total_items' => 0,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $itemA->id,
        'quantity' => 2,
        'price' => 10_000,
        'total' => 20_000,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $itemB->id,
        'quantity' => 3,
        'price' => 10_000,
        'total' => 30_000,
    ]);

    expect($sell->fresh()->displayTotalItems())->toBe(5.0);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('data-testid="tx-total-items"', false)
        ->assertSee('>5<', false)
        ->assertSee('2 lines', false)
        ->assertSee('data-testid="tx-total-items-summary"', false)
        ->assertSee('data-testid="tx-total-items-footer"', false);
});

it('renders prominent overview fields and internal metadata on show', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Overview Gudang']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Overview Customer']);
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-OVERVIEW',
        'date' => '2026-03-15',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -10_000,
        'real_total' => -10_000,
        'total_items' => 1,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('data-testid="tx-items-table"', false)
        ->assertSee('<table x-ref="itemsTable"', false)
        ->assertSee('data-testid="tx-show-overview"', false)
        ->assertSee('data-testid="tx-show-date"', false)
        ->assertSee('15/03/2026', false)
        ->assertSee('data-testid="tx-show-type"', false)
        ->assertSee('data-testid="tx-show-party-sender"', false)
        ->assertSee('data-testid="tx-show-party-receiver"', false)
        ->assertSee('Overview Gudang', false)
        ->assertSee('Overview Customer', false)
        ->assertSee('data-testid="tx-show-submit-source"', false)
        ->assertSee('aria submit', false)
        ->assertSee('data-testid="tx-show-created-by"', false)
        ->assertSee($this->user->name, false)
        ->assertSee('data-testid="tx-show-created-at"', false);
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
