<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;


test('cash in transaction can be stored', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $response = $this->actingAs($user)->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $customer->id,
                'invoice' => 'INV-001',
                'note' => 'Test payment',
                'total' => 1000,
            ],
        ],
    ]);

    $lastTransaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $lastTransaction));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_IN,
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 1000,
    ]);

    // Check balance updates
    // TransactionService uses AddrbookStat for overall balance
    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bank->id,
        'balance' => 1000,
    ]);

    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $customer->id,
        'balance' => 1000,
    ]);
});

test('cash in accepts decimal totals', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $this->actingAs($user)->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $customer->id,
                'total' => 1234.56,
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_IN,
        'total' => 1234.56,
    ]);
});

test('multiple cash in rows create multiple transactions', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer1 = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $customer2 = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $this->actingAs($user)->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $customer1->id,
                'total' => 500,
            ],
            [
                'customer_id' => $customer2->id,
                'total' => 1500,
            ],
        ],
    ]);

    $this->assertEquals(2, Transaction::where('type', Transaction::TYPE_CASH_IN)->count());

    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bank->id,
        'balance' => 2000,
    ]);
});

test('cash in rejects more than seven rows', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customers = Addrbook::factory()->count(8)->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $response = $this->actingAs($user)->postJson(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => $customers->map(fn ($customer) => [
            'customer_id' => $customer->id,
            'total' => 100,
        ])->all(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('cash in accepts supplier as source party', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $supplier = Addrbook::factory()->supplier()->create();

    $this->actingAs($user)->post(route('transactions.cash-in.store'), [
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
        'type' => Transaction::TYPE_CASH_IN,
        'sender_id' => $supplier->id,
        'receiver_id' => $bank->id,
        'total' => 100,
    ]);
});

test('cash in rejects warehouse as source party', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)->postJson(route('transactions.cash-in.store'), [
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

test('cash in accepts ledger account as source party', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $ledger = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT, 'name' => 'Biaya Admin']);

    $this->actingAs($user)->post(route('transactions.cash-in.store'), [
        'date' => now()->format('Y-m-d'),
        'account_id' => $bank->id,
        'items' => [
            [
                'customer_id' => $ledger->id,
                'total' => 250,
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_IN,
        'sender_id' => $ledger->id,
        'receiver_id' => $bank->id,
        'total' => 250,
    ]);
});
