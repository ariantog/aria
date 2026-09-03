<?php

use App\Models\Transaction;
use App\Services\TransactionService;

it('posts buy on sender and sell on receiver', function () {
    expect(TransactionService::signedBalanceDeltas(Transaction::TYPE_BUY, 100.0))
        ->toBe(['sender' => 100.0, 'receiver' => null])
        ->and(TransactionService::signedBalanceDeltas(Transaction::TYPE_SELL, -50.0))
        ->toBe(['sender' => null, 'receiver' => -50.0]);
});

it('posts transfer sender as signed amount and receiver as the opposite', function () {
    expect(TransactionService::signedBalanceDeltas(Transaction::TYPE_TRANSFER, -1000.0))
        ->toBe(['sender' => -1000.0, 'receiver' => 1000.0]);
});

it('posts adjust sender opposite the signed amount', function () {
    expect(TransactionService::signedBalanceDeltas(Transaction::TYPE_ADJUST, 250.0))
        ->toBe(['sender' => -250.0, 'receiver' => 250.0]);
});

it('does not post money balances for move or production', function () {
    expect(TransactionService::signedBalanceDeltas(Transaction::TYPE_MOVE, -10.0))
        ->toBe(['sender' => null, 'receiver' => null])
        ->and(TransactionService::signedBalanceDeltas(Transaction::TYPE_PRODUCTION, 10.0))
        ->toBe(['sender' => null, 'receiver' => null]);
});
