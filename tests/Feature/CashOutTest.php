<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

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
                'invoice_number' => 'CO-001',
                'note' => 'Test payment',
                'total' => 1000,
            ],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_id' => $bank->id,    // Cashier/Bank is sender in Cash Out
        'receiver_id' => $recipient->id,
        'grand_total' => -1000,
    ]);

    // Check balance updates (AddrbookStat)
    // Both sides decrease balance in Cash Out
    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bank->id,
        'balance' => -1000,
    ]);

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $recipient->id,
        'balance' => -1000,
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

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bank->id,
        'balance' => -2000,
    ]);
});
