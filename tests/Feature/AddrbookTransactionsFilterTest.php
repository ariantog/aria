<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $this->customer = Addrbook::factory()->customer()->create();
});

it('filters bank addrbook transactions by stored total sign', function () {
    $match = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => 'BANK-TX-MATCH',
        'sender_id' => $this->customer->id,
        'receiver_id' => $this->bank->id,
        'total' => 5_472_500,
    ]);
    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => 'BANK-TX-OTHER',
        'sender_id' => $this->customer->id,
        'receiver_id' => $this->bank->id,
        'total' => 100_000,
    ]);

    $url = route('addrbook.type.transactions', ['type' => 'bank', 'addrbook' => $this->bank->id]);

    $this->get($url.'?total=5472500')
        ->assertOk()
        ->assertSee('BANK-TX-MATCH', false)
        ->assertDontSee('BANK-TX-OTHER', false);

    expect($match->id)->toBeGreaterThan(0);
});

it('filters bank addrbook transactions when amount is entered opposite sign', function () {
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'invoice' => 'BANK-OUT-MATCH',
        'sender_id' => $this->bank->id,
        'receiver_id' => $supplier->id,
        'total' => -5_365_000,
    ]);
    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'invoice' => 'BANK-OUT-OTHER',
        'sender_id' => $this->bank->id,
        'receiver_id' => $supplier->id,
        'total' => -50_000,
    ]);

    $url = route('addrbook.type.transactions', ['type' => 'bank', 'addrbook' => $this->bank->id]);

    $this->get($url.'?total=5365000')
        ->assertOk()
        ->assertSee('BANK-OUT-MATCH', false)
        ->assertDontSee('BANK-OUT-OTHER', false);
});

it('filters warehouse addrbook transactions by invoice substring', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $customer = Addrbook::factory()->customer()->create();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'WH-INV-1001',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -100_000,
    ]);
    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'WH-INV-2002',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -50_000,
    ]);

    $url = route('addrbook.type.transactions', ['type' => 'warehouse', 'addrbook' => $warehouse->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('data-testid="addrbook-tx-filter-invoice"', false);

    $this->get($url.'?invoice=1001')
        ->assertOk()
        ->assertSee('WH-INV-1001', false)
        ->assertDontSee('WH-INV-2002', false);
});