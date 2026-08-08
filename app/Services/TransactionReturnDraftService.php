<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class TransactionReturnDraftService
{
    public function targetTypeSlug(Transaction $transaction): ?string
    {
        $type = $transaction->type instanceof \BackedEnum
            ? $transaction->type->value
            : (int) $transaction->type;

        return match ($type) {
            Transaction::TYPE_SELL => 'return',
            Transaction::TYPE_BUY => 'return-supplier',
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
                'transaction' => ['Only buy or sell transactions can be returned.'],
            ]);
        }

        if ($transaction->details->isEmpty()) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction has no items to return.'],
            ]);
        }

        $newSender = $transaction->receiver;
        $newReceiver = $transaction->sender;

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
                'warehouse_items' => $item->warehouseItems->map(fn ($wi) => [
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
            'invoice_number' => (string) $transaction->invoice_number,
            'note' => $returnNote,
            'discount_percent' => (float) ($transaction->discount_percent ?? 0),
            'adjustment' => (float) ($transaction->adjustment ?? 0),
            'items' => $prefillItems,
        ];
    }
}
