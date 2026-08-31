<?php

namespace App\Services\Jubelio;

use App\Models\Transaction;

/**
 * Outbound Jubelio stock sync — the map of vague L10 column names.
 *
 * Two separate Jubelio integrations exist. Do not mix them:
 *
 * 1. Inbound (Jubelio → Aria): webhooks / cron create SELL or RETURN rows with
 *    submit_type = Transaction::SUBMIT_TYPE_JUBELIO (2). Those already exist in
 *    Jubelio. Never "Push to Jubelio" them.
 * 2. Outbound (Aria → Jubelio): a human created a manual txn (submit_type = 1)
 *    and clicks Push. AdjustStock POSTs a warehouse stock adjustment to
 *    https://api2.jubelio.com/inventory/adjustments/warehouse. Success is a
 *    Jubelio item_adj_id, not a generic HTTP 200.
 *
 * Mapping:
 * - Aria warehouse → Jubelio location: jubeliosyncs.warehouse_id / jubelio_location_id
 * - Aria item → Jubelio item: items.jubelio_item_id
 *
 * A move is two independent adjustments (deduct sender, add receiver), not a
 * Jubelio transfer document. Sell/return-supplier only push the sender.
 * Buy/return only push the receiver.
 *
 * Legacy column names (do NOT rename — production MySQL / L10):
 *
 * | Column            | Meaning |
 * |-------------------|---------|
 * | a_submit_by       | users.id who successfully pushed the SENDER warehouse (side A). Null = not synced. |
 * | b_submit_by       | users.id who successfully pushed the RECEIVER warehouse (side B). Null = not synced. |
 * | a_reference_id    | Jubelio item_adj_id for the sender-warehouse adjustment. |
 * | b_reference_id    | Jubelio item_adj_id for the receiver-warehouse adjustment. |
 * | submit_a_count    | Sender-side push attempts. Warning = count > 0 AND a_submit_by is null. |
 * | submit_b_count    | Receiver-side push attempts. Warning = count > 0 AND b_submit_by is null. |
 * | submit_type       | 1 = Aria manual (may push). 2 = created from Jubelio (do not push). |
 * | sync_hide         | N/Y hide row on the Jubelio transaction-sync list. |
 *
 * Request params used by AdjustStock (also L10 names):
 * - side 1 = sender (A), side 2 = receiver (B)
 * - whType 2 = use transaction.sender_id, whType 1 = use transaction.receiver_id
 * - adjustType 2 = deduct qty, adjustType 1 = add qty
 *
 * A 200 from Jubelio is not success unless the body has a positive item_adj_id
 * (or id). A 200 with only {message: "..."} or a listing {data, totalCount} means
 * nothing was created. We did not persist the live body for the Aug 2026 move
 * incident, so the exact Jubelio reject reason is unknown — check laravel.log
 * "Jubelio stock adjustment" lines for the next push.
 */
final class JubelioStockSync
{
    /** Sender warehouse. Columns a_submit_by / a_reference_id / submit_a_count. */
    public const SIDE_SENDER = 1;

    /** Receiver warehouse. Columns b_submit_by / b_reference_id / submit_b_count. */
    public const SIDE_RECEIVER = 2;

    /** AdjustStock $whType: resolve transaction.receiver_id. */
    public const WAREHOUSE_RECEIVER = 1;

    /** AdjustStock $whType: resolve transaction.sender_id. */
    public const WAREHOUSE_SENDER = 2;

    /** Positive qty_in_base (stock in). */
    public const ADJUST_ADD = 1;

    /** Negative qty_in_base (stock out). */
    public const ADJUST_DEDUCT = 2;

    public static function isSenderSide(int $side): bool
    {
        return $side === self::SIDE_SENDER;
    }

    public static function warehouseId(Transaction $transaction, int $whType): int
    {
        return $whType === self::WAREHOUSE_RECEIVER
            ? (int) $transaction->receiver_id
            : (int) $transaction->sender_id;
    }

    public static function signedQty(float $quantity, int $adjustType): float
    {
        return $adjustType === self::ADJUST_ADD ? $quantity : -$quantity;
    }

    /**
     * Types that push the sender warehouse (side A / deduct).
     *
     * @return list<int>
     */
    public static function senderPushTypes(): array
    {
        return [
            Transaction::TYPE_SELL,
            Transaction::TYPE_RETURN_SUPPLIER,
            Transaction::TYPE_MOVE,
        ];
    }

    /**
     * Types that push the receiver warehouse (side B / add).
     *
     * @return list<int>
     */
    public static function receiverPushTypes(): array
    {
        return [
            Transaction::TYPE_BUY,
            Transaction::TYPE_RETURN,
            Transaction::TYPE_MOVE,
        ];
    }
}
