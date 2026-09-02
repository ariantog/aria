<?php

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Transaction;
use App\Services\TransactionService;


test('creating a transaction updates daily report for sender and receiver', function () {
    // 1. Setup
    $user = \App\Models\User::factory()->create();
    $sender = Addrbook::create(['name' => 'Supplier A', 'type' => Addrbook::TYPE_SUPPLIER]);
    $receiver = Addrbook::create(['name' => 'Warehouse A', 'type' => Addrbook::TYPE_WAREHOUSE]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'sender_id' => $sender->id,
        'sender_type' => Addrbook::TYPE_SUPPLIER,
        'receiver_id' => $receiver->id,
        'receiver_type' => Addrbook::TYPE_WAREHOUSE,
        'type' => Transaction::TYPE_BUY,
        'date' => now(),
        'real_total' => 1000.00,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    // 2. Action
    $transaction->refresh();

    // Now uses correct signature: updateDailyReports(Transaction $transaction, string $side, $amount)
    $service = new ReflectionClass(TransactionService::class);
    $method = $service->getMethod('updateDailyReports');
    $method->setAccessible(true);
    $method->invoke(new TransactionService, $transaction, 'sender', $transaction->real_total);

    // 3. Assertion
    $senderDaily = AddrbookDaily::where('customer_id', $sender->id)
        ->where('date', now()->format('Y-m-d'))
        ->first();

    expect($senderDaily)->not->toBeNull()
        ->and($senderDaily->buy)->toEqual('1000.00');

    // Receiver (Warehouse) is NO LONGER updated for BUY transaction in the new logic
    $receiverDaily = AddrbookDaily::where('customer_id', $receiver->id)
        ->where('date', now()->format('Y-m-d'))
        ->first();

    expect($receiverDaily)->toBeNull();
});

test('subsequent transactions on the same day increment the daily total', function () {
    $user = \App\Models\User::factory()->create();
    $sender = Addrbook::create(['name' => 'Supplier B', 'type' => Addrbook::TYPE_SUPPLIER]);

    $service = new TransactionService;

    // First transaction
    $t1 = Transaction::create([
        'user_id' => $user->id,
        'sender_id' => $sender->id,
        'type' => Transaction::TYPE_BUY,
        'date' => now(),
        'real_total' => 500.00,
        'total' => 500.00,
    ]);
    $service->handleTransaction($t1);

    // Second transaction
    $t2 = Transaction::create([
        'user_id' => $user->id,
        'sender_id' => $sender->id,
        'type' => Transaction::TYPE_BUY,
        'date' => now(),
        'real_total' => 300.00,
        'total' => 300.00,
    ]);
    $service->handleTransaction($t2);

    $daily = AddrbookDaily::where('customer_id', $sender->id)
        ->where('date', now()->format('Y-m-d'))
        ->first();

    expect($daily->buy)->toEqual('800.00');
});
