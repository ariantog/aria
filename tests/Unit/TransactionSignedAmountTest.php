<?php

use App\Models\Transaction;

it('signs transaction totals by type', function () {
    expect(Transaction::signedAmount(Transaction::TYPE_BUY, 100))->toBe(100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_RETURN, 100))->toBe(100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_CASH_IN, 100))->toBe(100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_SELL, 100))->toBe(-100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_RETURN_SUPPLIER, 100))->toBe(-100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_CASH_OUT, 100))->toBe(-100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_TRANSFER, 100))->toBe(-100.0)
        ->and(Transaction::signedAmount(Transaction::TYPE_MOVE, 0))->toBe(0.0);
});
