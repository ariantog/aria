<?php

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Addrbook::factory()->warehouse()->create();
    $this->supplier = Addrbook::factory()->supplier()->create(['name' => 'PT Supplier']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'PT Customer']);
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'BCA Kas']);
});

it('shows linked sell on a cash-in transaction page', function () {
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV/LINK/SELL/1',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'total' => -1_000_000,
        'real_total' => -1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $cashIn = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $sell->invoice,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $this->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $this->bank->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $cashIn))
        ->assertOk()
        ->assertSee('data-testid="invoice-linked-transactions"', false)
        ->assertSee('Linked sell', false)
        ->assertSee('#'.$sell->id, false)
        ->assertSee($this->customer->name, false);
});

it('shows linked cash-in on a sell transaction page', function () {
    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV/LINK/SELL/2',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'total' => -750_000,
        'real_total' => -750_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $cashIn = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $sell->invoice,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $this->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $this->bank->id,
        'total' => 750_000,
        'real_total' => 750_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee('Linked cash-in', false)
        ->assertSee(route('transactions.show', $cashIn), false);
});

it('shows linked buy on a cash-out transaction page', function () {
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'INV/LINK/BUY/1',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $this->supplier->id,
        'total' => 2_000_000,
        'real_total' => 2_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $cashOut = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'invoice' => $buy->invoice,
        'sender_type' => (string) Addrbook::TYPE_BANK,
        'sender_id' => $this->bank->id,
        'receiver_type' => (string) Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $this->supplier->id,
        'total' => -2_000_000,
        'real_total' => -2_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $cashOut))
        ->assertOk()
        ->assertSee('data-testid="invoice-linked-transactions"', false)
        ->assertSee('Linked buy', false)
        ->assertSee('#'.$buy->id, false)
        ->assertSee($this->supplier->name, false);
});

it('shows linked cash-out on a buy transaction page', function () {
    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'INV/LINK/BUY/2',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $this->supplier->id,
        'total' => 1_250_000,
        'real_total' => 1_250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $cashOut = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'invoice' => $buy->invoice,
        'sender_type' => (string) Addrbook::TYPE_BANK,
        'sender_id' => $this->bank->id,
        'receiver_type' => (string) Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $this->supplier->id,
        'total' => -1_250_000,
        'real_total' => -1_250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $buy))
        ->assertOk()
        ->assertSee('data-testid="invoice-linked-transactions"', false)
        ->assertSee('Linked cash-out', false)
        ->assertSee(route('transactions.show', $cashOut), false);
});

it('uses invoice maker settlement instead of duplicate linked sell on cash-in', function () {
    $invoiceNumber = 'INV/LINK/MAKER/1';

    StandaloneInvoice::factory()->create([
        'number' => $invoiceNumber,
        'subtotal' => 1_000_000,
        'discount_amount' => 0,
        'user_id' => $this->user->id,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $invoiceNumber,
        'total' => -1_000_000,
        'real_total' => -1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $cashIn = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $invoiceNumber,
        'sender_type' => (string) Addrbook::TYPE_CUSTOMER,
        'sender_id' => $this->customer->id,
        'receiver_type' => (string) Addrbook::TYPE_BANK,
        'receiver_id' => $this->bank->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $cashIn))
        ->assertOk()
        ->assertSee('Invoice Maker', false)
        ->assertSee('Linked sell', false)
        ->assertSee('#'.$sell->id, false)
        ->assertDontSee('data-testid="invoice-linked-transactions"', false);
});
