<?php

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Support\ItemTransactionPresentation;

uses(Tests\TestCase::class);

function makePresentationDetailModel(int $type, float $qty, array $transactionAttrs = [], array $detailAttrs = []): TransactionDetail
{
    $transaction = new Transaction(array_merge([
        'type' => $type,
        'sender_id' => 10,
        'receiver_id' => 20,
    ], $transactionAttrs));

    $detail = new TransactionDetail(array_merge([
        'transaction_type' => $type,
        'quantity' => $qty,
        'sender_id' => $transaction->sender_id,
        'receiver_id' => $transaction->receiver_id,
    ], $detailAttrs));

    $detail->setRelation('transaction', $transaction);

    return $detail;
}

it('uses global stock signs when no party filter is active', function (int $type, float $expected) {
    $detail = makePresentationDetailModel($type, 5);

    expect(ItemTransactionPresentation::signedQuantity($detail))->toBe($expected);
})->with([
    'buy' => [Transaction::TYPE_BUY, 5.0],
    'sell' => [Transaction::TYPE_SELL, -5.0],
    'return' => [Transaction::TYPE_RETURN, 5.0],
    'return supplier' => [Transaction::TYPE_RETURN_SUPPLIER, -5.0],
    'production' => [Transaction::TYPE_PRODUCTION, 5.0],
    'move' => [Transaction::TYPE_MOVE, 0.0],
]);

it('uses sender and receiver perspective when a party filter is active', function () {
    $detail = makePresentationDetailModel(Transaction::TYPE_MOVE, 8, [
        'sender_id' => 101,
        'receiver_id' => 202,
    ]);

    expect(ItemTransactionPresentation::signedQuantity($detail, 101))->toBe(-8.0)
        ->and(ItemTransactionPresentation::signedQuantity($detail, 202))->toBe(8.0);
});

it('prefers transaction description when line notes are blank', function () {
    $detail = makePresentationDetailModel(Transaction::TYPE_SELL, 1, [
        'description' => 'OL 09.09.26',
        'notes' => null,
    ], [
        'notes' => '',
    ]);

    expect(ItemTransactionPresentation::descriptionText($detail))->toBe('OL 09.09.26');
});

it('falls back to transaction notes when description is blank', function () {
    $detail = makePresentationDetailModel(Transaction::TYPE_SELL, 1, [
        'description' => '',
        'notes' => 'OL 09.09.26',
    ]);

    expect(ItemTransactionPresentation::descriptionText($detail))->toBe('OL 09.09.26');
});

it('prefers non-empty line notes over header text', function () {
    $detail = makePresentationDetailModel(Transaction::TYPE_SELL, 1, [
        'description' => 'Header description',
    ], [
        'notes' => 'TITIP SALES 11-15 AGST 2026 - 158pcs',
    ]);

    expect(ItemTransactionPresentation::descriptionText($detail))->toBe('TITIP SALES 11-15 AGST 2026 - 158pcs');
});

it('renders neutral quantity text for zero net move rows', function () {
    expect(ItemTransactionPresentation::formattedSignedQuantity(0.0, 12.0))->toBe('12')
        ->and(ItemTransactionPresentation::quantityToneClass(0.0))->toBe('text-gray-500');
});
