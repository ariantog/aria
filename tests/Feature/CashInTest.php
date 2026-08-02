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
                'invoice_number' => 'INV-001',
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
        'grand_total' => 1000,
    ]);

    // Check balance updates
    // TransactionService uses AddrbookStat for overall balance
    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bank->id,
        'balance' => 1000,
    ]);

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $customer->id,
        'balance' => 1000,
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

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bank->id,
        'balance' => 2000,
    ]);
});
