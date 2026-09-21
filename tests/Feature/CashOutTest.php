<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;


test('cash out transaction can be stored', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $recipient = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $response = $this->actingAs($user)->post(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $recipient->id,
                'invoice' => 'CO-001',
                'note' => 'Test payment',
                'total' => 1000,
            ],
        ],
    ]);

    $lastTransaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $lastTransaction));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_id' => $bank->id,    // Cashier/Bank is sender in Cash Out
        'receiver_id' => $recipient->id,
        'total' => -1000,
    ]);

    // Check balance updates (AddrbookStat)
    // Both sides decrease balance in Cash Out
    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bank->id,
        'balance' => -1000,
    ]);

    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $recipient->id,
        'balance' => -1000,
    ]);

    $this->assertDatabaseHas('customer_class', [
        'customer_id' => $bank->id,
        'date' => now()->format('Y-m-d'),
        'buy' => -1000,
        'adjust' => 0,
        'depreciation' => 0,
        'class' => '',
    ]);
});

test('multiple cash out rows create multiple transactions', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $recipient1 = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $recipient2 = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $this->actingAs($user)->post(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $recipient1->id,
                'total' => 500,
            ],
            [
                'customer_id' => $recipient2->id,
                'total' => 1500,
            ],
        ],
    ]);

    $this->assertEquals(2, Transaction::where('type', Transaction::TYPE_CASH_OUT)->count());

    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bank->id,
        'balance' => -2000,
    ]);
});

test('cash out accepts decimal totals', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $recipient = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $this->actingAs($user)->post(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $recipient->id,
                'total' => 99.75,
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_OUT,
        'total' => -99.75,
    ]);
});

test('cash out accepts supplier as recipient party', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $supplier = Addrbook::factory()->supplier()->create();

    $this->actingAs($user)->post(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $supplier->id,
                'total' => 100,
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_id' => $bank->id,
        'receiver_id' => $supplier->id,
        'total' => -100,
    ]);
});

test('cash out rejects warehouse as recipient party', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)->postJson(route('transactions.cash-out.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $warehouse->id,
                'total' => 100,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.customer_id']);
});
