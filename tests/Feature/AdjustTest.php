<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;


test('adjust transaction can be stored and updates balances', function () {
    $user = User::factory()->create();
    $account = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    // Adjust: Account (Sender/Debit+) -> Customer (Receiver/Credit+)
    // Debit sender means sender balance decreases
    // Credit receiver means receiver balance increases
    $response = $this->actingAs($user)->post(route('transactions.adjust.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $account->id,
        'receiver' => $customer->id,
        'total' => 1000,
        'invoice' => 'ADJ-001',
        'description' => 'Correction',
    ]);

    $lastTransaction = Transaction::latest('id')->first();
    $response->assertRedirect(route('transactions.show', $lastTransaction));

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_ADJUST,
        'sender_id' => $account->id,
        'receiver_id' => $customer->id,
        'grand_total' => 1000,
    ]);

    // Check balance updates (AddrbookStat)
    // Sender decreases (-1000), Receiver increases (+1000)
    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $account->id,
        'balance' => -1000,
    ]);

    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $customer->id,
        'balance' => 1000,
    ]);
});

test('adjust fails if sender and receiver are the same', function () {
    $user = User::factory()->create();
    $account = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT]);

    $response = $this->actingAs($user)->post(route('transactions.adjust.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $account->id,
        'receiver' => $account->id,
        'total' => 1000,
    ]);

    $response->assertSessionHasErrors(['receiver']);
});

test('adjust requires sender and receiver', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.adjust.store'), [
        'date' => now()->format('Y-m-d'),
        'total' => 1000,
    ]);

    $response->assertSessionHasErrors(['sender', 'receiver']);
});
