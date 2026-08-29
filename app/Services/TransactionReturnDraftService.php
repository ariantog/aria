<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class TransactionReturnDraftService
{
    public function targetTypeSlug(Transaction $transaction): ?string
    {
        $type = (int) $transaction->type;

        return match ($type) {
            Transaction::TYPE_SELL => 'return',
            Transaction::TYPE_BUY => 'return-supplier',
            Transaction::TYPE_MOVE => 'move',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrefill(Transaction $transaction): array
    {
        $transaction->loadMissing(['details.item.warehouseItems', 'sender', 'receiver']);

        if (! $this->targetTypeSlug($transaction)) {
            throw ValidationException::withMessages([
                'transaction' => ['This transaction type cannot be returned.'],
            ]);
        }

        if ($transaction->details->isEmpty()) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction has no items to return.'],
            ]);
        }

        $newSender = $transaction->receiver;
        $newReceiver = $transaction->sender;

        if (! $newSender || ! $newReceiver) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction is missing sender or receiver.'],
            ]);
        }

        $prefillItems = [];
        foreach ($transaction->details as $detail) {
            $item = $detail->item;
            if (! $item) {
                continue;
            }

            $prefillItems[] = [
                'item_id' => (string) $item->id,
                'code' => $item->code,
                'name' => $item->getItemName(),
                'quantity' => (float) $detail->quantity,
                'price' => (float) $detail->price,
                'discount' => (float) $detail->discount,
                'subtotal' => (float) $detail->total,
                'note' => $detail->notes ?? '',
                'jubelio_item_id' => (int) ($item->jubelio_item_id ?? 0),
                'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ];
        }

        if ($prefillItems === []) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction has no items to return.'],
            ]);
        }

        $notes = trim((string) $transaction->notes);
        $returnNote = $notes !== '' ? "return\n{$notes}" : 'return';

        return [
            'sender_id' => (string) $newSender->id,
            'sender' => [
                'id' => $newSender->id,
                'name' => $newSender->name,
                'ppn' => (bool) $newSender->ppn,
            ],
            'receiver_id' => (string) $newReceiver->id,
            'receiver' => [
                'id' => $newReceiver->id,
                'name' => $newReceiver->name,
                'ppn' => (bool) $newReceiver->ppn,
            ],
            'invoice' => (string) $transaction->invoice,
            'note' => $returnNote,
            'discount' => (float) ($transaction->discount ?? 0),
            'adjustment' => (float) ($transaction->adjustment ?? 0),
            'items' => $prefillItems,
        ];
    }
}
