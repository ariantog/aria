<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;


test('bank transfer can be stored and updates balances', function () {
    $user = User::factory()->create();
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 5000,
        'invoice' => 'TRF-001',
        'description' => 'Internal transfer',
    ]);

    $lastTransaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $lastTransaction));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_TRANSFER,
        'sender_id' => $bankSource->id,
        'receiver_id' => $bankDest->id,
        'total' => 5000,
        'real_total' => 5000,
    ]);

    // Check balance updates (AddrbookStat)
    // Source decreases (-5000), Dest increases (+5000)
    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bankSource->id,
        'balance' => -5000,
    ]);

    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $bankDest->id,
        'balance' => 5000,
    ]);
});

test('bank transfer fails if sender and receiver are the same', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bank->id,
        'receiver' => $bank->id,
        'total' => 5000,
    ]);

    $response->assertSessionHasErrors(['receiver']);
});

test('transfer accepts virtual accounts as source or destination', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'BCA Main']);
    $vAccount = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_ACCOUNT, 'name' => 'Petty Cash']);

    $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $vAccount->id,
        'receiver' => $bank->id,
        'total' => 2500,
    ]);

    $lastTransaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $lastTransaction));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_TRANSFER,
        'sender_id' => $vAccount->id,
        'sender_type' => Addrbook::TYPE_V_ACCOUNT,
        'receiver_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'total' => 2500,
        'real_total' => 2500,
    ]);
});

test('transfer rejects non-account addrbook types', function () {
    $user = User::factory()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $this->actingAs($user)->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $customer->id,
        'receiver' => $bank->id,
        'total' => 1000,
    ])->assertSessionHasErrors(['sender']);
});
