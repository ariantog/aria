<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\TransactionDetail;

class ItemTransactionPresentation
{
    /**
     * Signed quantity for an item transaction line.
     *
     * With a party filter: sender role is negative, receiver role is positive
     * (matches TransactionService::updateStock warehouse deltas).
     *
     * Without a party filter: net company stock change (matches items.qty updates).
     */
    public static function signedQuantity(TransactionDetail $detail, ?int $partyId = null): float
    {
        $qty = abs((float) $detail->quantity);

        if ($partyId !== null) {
            return self::signedQuantityForParty($detail, $partyId, $qty);
        }

        return self::signedQuantityForGlobalStock((int) $detail->transaction_type, $qty);
    }

    /**
     * Description text aligned with transaction show (header description/notes),
     * with non-empty line notes taking precedence.
     */
    public static function descriptionText(TransactionDetail $detail): string
    {
        $transaction = $detail->transaction;

        foreach ([
            $detail->notes,
            $transaction?->description,
            $transaction?->notes,
        ] as $candidate) {
            $text = trim((string) ($candidate ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '-';
    }

    public static function quantityToneClass(float $signedQty): string
    {
        if ($signedQty < 0) {
            return 'text-rose-500';
        }

        if ($signedQty > 0) {
            return 'text-emerald-500';
        }

        return 'text-gray-500';
    }

    public static function formattedSignedQuantity(float $signedQty, float $rawQuantity): string
    {
        if ($signedQty === 0.0) {
            return format_amount($rawQuantity);
        }

        $prefix = $signedQty > 0 ? '+' : '-';

        return $prefix.format_amount($rawQuantity);
    }

    private static function signedQuantityForParty(TransactionDetail $detail, int $partyId, float $qty): float
    {
        $transaction = $detail->transaction;
        $senderId = (int) ($detail->sender_id ?: $transaction?->sender_id);
        $receiverId = (int) ($detail->receiver_id ?: $transaction?->receiver_id);

        if ($senderId === $partyId) {
            return -$qty;
        }

        if ($receiverId === $partyId) {
            return $qty;
        }

        return self::signedQuantityForGlobalStock((int) $detail->transaction_type, $qty);
    }

    private static function signedQuantityForGlobalStock(int $type, float $qty): float
    {
        return match ($type) {
            Transaction::TYPE_SELL,
            Transaction::TYPE_RETURN_SUPPLIER => -$qty,
            Transaction::TYPE_MOVE => 0.0,
            default => $qty,
        };
    }
}
