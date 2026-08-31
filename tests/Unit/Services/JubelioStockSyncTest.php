<?php

use App\Models\Transaction;
use App\Services\Jubelio\JubelioStockSync;

it('treats side A as sender deduct and side B as receiver add', function () {
    expect(JubelioStockSync::SIDE_SENDER)->toBe(1)
        ->and(JubelioStockSync::SIDE_RECEIVER)->toBe(2)
        ->and(JubelioStockSync::isSenderSide(1))->toBeTrue()
        ->and(JubelioStockSync::isSenderSide(2))->toBeFalse()
        ->and(JubelioStockSync::signedQty(3, JubelioStockSync::ADJUST_ADD))->toBe(3.0)
        ->and(JubelioStockSync::signedQty(3, JubelioStockSync::ADJUST_DEDUCT))->toBe(-3.0);
});

it('lists which transaction types push sender vs receiver warehouses', function () {
    expect(JubelioStockSync::senderPushTypes())->toBe([
        Transaction::TYPE_SELL,
        Transaction::TYPE_RETURN_SUPPLIER,
        Transaction::TYPE_MOVE,
    ])->and(JubelioStockSync::receiverPushTypes())->toBe([
        Transaction::TYPE_BUY,
        Transaction::TYPE_RETURN,
        Transaction::TYPE_MOVE,
    ]);
});
