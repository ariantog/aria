<?php

namespace App\Support\Mobile;

use App\Models\Transaction;
use App\Models\User;

class MobileTransactionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listRow(Transaction $transaction, User $user): array
    {
        $hideBank = ! $user->is_superadmin && $user->can('addrbook-bank-account-hidden-balance');

        return [
            'id' => $transaction->id,
            'date' => $transaction->date?->toDateString(),
            'invoice' => $transaction->invoice,
            'type' => self::typePayload($transaction),
            'total' => $transaction->displaySignedGrandTotal(),
            'total_items' => $transaction->total_items,
            'sender' => self::partyPayload($transaction, 'sender', $hideBank),
            'receiver' => self::partyPayload($transaction, 'receiver', $hideBank),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Transaction $transaction, User $user): array
    {
        $hideBank = ! $user->is_superadmin && $user->can('addrbook-bank-account-hidden-balance');

        $details = $transaction->details->map(function ($detail) {
            $item = $detail->item;

            return [
                'id' => $detail->id,
                'item_id' => $detail->item_id,
                'code' => $item?->code,
                'name' => $item?->name,
                'quantity' => (float) $detail->quantity,
                'price' => (float) $detail->price,
                'discount' => (float) $detail->discount,
                'total' => (float) $detail->total,
            ];
        })->values()->all();

        return [
            'id' => $transaction->id,
            'date' => $transaction->date?->toDateString(),
            'due' => $transaction->due?->toDateString(),
            'invoice' => $transaction->invoice,
            'type' => self::typePayload($transaction),
            'status' => (int) $transaction->status,
            'note' => $transaction->note,
            'total' => $transaction->displaySignedGrandTotal(),
            'discount' => (float) $transaction->discount,
            'adjustment' => (float) $transaction->adjustment,
            'ppn' => (float) $transaction->ppn,
            'total_items' => $transaction->displayTotalItems(),
            'sender' => self::partyPayload($transaction, 'sender', $hideBank, includeBalance: true),
            'receiver' => self::partyPayload($transaction, 'receiver', $hideBank, includeBalance: true),
            'sender_balance' => $hideBank && $transaction->sender?->type_slug === 'bank'
                ? null
                : $transaction->sender_balance,
            'receiver_balance' => $hideBank && $transaction->receiver?->type_slug === 'bank'
                ? null
                : $transaction->receiver_balance,
            'details' => $details,
        ];
    }

    /**
     * @return array{id: int, slug: string, label: string}
     */
    public static function typePayload(Transaction $transaction): array
    {
        $typeId = (int) $transaction->type;

        return [
            'id' => $typeId,
            'slug' => self::typeSlugFromId($typeId),
            'label' => $transaction->getTypeLabel(),
        ];
    }

    public static function typeSlugFromId(int $typeId): string
    {
        $rules = config('transaction_rules', []);
        foreach ($rules as $slug => $rule) {
            if ((int) ($rule['id'] ?? 0) === $typeId) {
                return (string) $slug;
            }
        }

        return 'adjust';
    }

    /**
     * @return array{id: int, name: string, balance?: float|null}|null
     */
    private static function partyPayload(
        Transaction $transaction,
        string $role,
        bool $hideBank,
        bool $includeBalance = false,
    ): ?array {
        $party = $transaction->{$role};
        if ($party === null) {
            return null;
        }

        $payload = [
            'id' => $party->id,
            'name' => $party->name,
        ];

        if ($includeBalance) {
            $balanceKey = $role.'_balance';
            $storedBalance = $transaction->{$balanceKey};
            if ($hideBank && $party->type_slug === 'bank') {
                $payload['balance'] = null;
            } else {
                $payload['balance'] = $storedBalance !== null ? (float) $storedBalance : null;
            }
        }

        return $payload;
    }
}
