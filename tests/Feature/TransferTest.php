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
        'grand_total' => 5000,
    ]);

    // Check balance updates (AddrbookStat)
    // Source decreases (-5000), Dest increases (+5000)
    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bankSource->id,
        'balance' => -5000,
    ]);

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $bankDest->id,
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
