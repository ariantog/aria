<?php

use App\Models\Addrbook;
use App\Models\DeletedTransaction;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(); // id 1 — superadmin
});

it('uses seller income for legacy jubelio sell rows that double-count adjustment', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
    ]);

    expect($transaction->displayGrandTotal())->toBe(82350.0);
});

it('reads jubelio sell net receivable regardless of which header column stores it', function () {
    $legacyLayout = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -64000,
        'adjustment' => -21065,
        'real_total' => -42935,
    ]);

    $l10Layout = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'real_total' => -64000,
        'adjustment' => -21065,
        'total' => -42935,
    ]);

    expect($legacyLayout->displayGrandTotal())->toBe(42935.0)
        ->and($l10Layout->displayGrandTotal())->toBe(42935.0);
});

it('shows signed header total on transaction list and detail', function () {
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'total' => -150_000,
        'real_total' => -150_000,
        'invoice' => 'INV-SIGNED-SELL',
    ]);
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'total' => 75_000,
        'real_total' => 75_000,
        'invoice' => 'INV-SIGNED-BUY',
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('-150,000', false)
        ->assertSee('75,000', false);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('-150,000', false);
});

it('shows signed header total on legacy jubelio transaction detail', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-LEGACY-GRAND',
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'user_id' => $this->user->id,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 169000,
        'discount' => 0,
        'total' => 169000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('-82,350', false);
});

it('uses line-item sum for summary subtotal when header total is net receivable', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create();

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'sender_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
    ]);

    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 169000,
        'discount' => 0,
        'total' => 169000,
    ]);

    $transaction->load('details');

    expect($transaction->displaySummarySubtotal())->toBe(-169000.0)
        ->and($transaction->displayGrandTotal())->toBe(82350.0);
});

it('exposes display helpers on deleted transactions and renders deleted show', function () {
    $deleted = DeletedTransaction::create([
        'id' => 615223,
        'date' => now()->toDateString(),
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DELETED-GRAND',
        'total' => -82350,
        'adjustment' => -86650,
        'real_total' => -169000,
        'discount' => 0,
        'ppn' => 0,
        'total_items' => 1,
        'status' => Transaction::STATUS_COMPLETED,
        'deleted_at' => now(),
    ]);

    expect($deleted->displayGrandTotal())->toBe(82350.0);

    $this->actingAs($this->user)
        ->get(route('transactions.deleted.show', $deleted->id))
        ->assertOk()
        ->assertSee('-82,350', false);
});
