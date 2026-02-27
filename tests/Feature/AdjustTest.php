<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    $response->assertRedirect(route('transactions.index'));
    
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

test('adjust fails if both are accounts', function () {
    $user = User::factory()->create();
    $account1 = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT]);
    $account2 = Addrbook::factory()->create(['type' => Addrbook::TYPE_ACCOUNT]);

    $response = $this->actingAs($user)->post(route('transactions.adjust.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $account1->id,
        'receiver' => $account2->id,
        'total' => 1000,
    ]);

    $response->assertSessionHasErrors(['receiver']);
});

test('adjust fails if neither is account', function () {
    $user = User::factory()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER]);

    $response = $this->actingAs($user)->post(route('transactions.adjust.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $customer->id,
        'receiver' => $supplier->id,
        'total' => 1000,
    ]);

    $response->assertSessionHasErrors(['sender']);
});
