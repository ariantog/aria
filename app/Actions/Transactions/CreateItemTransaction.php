<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\CalculatesTransactionTotals;
use App\Http\Requests\StoreItemTransactionRequest;
use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateItemTransaction
{
    use CalculatesTransactionTotals;

    public function __construct(private readonly TransactionService $transactionService) {}

    public function execute(StoreItemTransactionRequest $request): Transaction
    {
        $type = $request->validatedType();
        $data = $request->validated();
        $sender = Addrbook::findOrFail($data['sender_id']);
        $receiver = Addrbook::findOrFail($data['receiver_id']);
        $cashInPayload = $request->cashInPayload();

        return DB::transaction(function () use ($type, $data, $sender, $receiver, $cashInPayload) {
            $this->validateStock($type, $sender, $data['items']);
            $transaction = $this->createTransaction($type, $data, $sender, $receiver);
            $this->createDetails($transaction, $data);
            $this->calculateAndSetTotals($transaction, $type, $data, $sender, $receiver);
            $this->transactionService->handleTransaction($transaction);

            if ($type === Transaction::TYPE_SELL && $cashInPayload) {
                app(CreateCashInFromSell::class)->execute($transaction->fresh(['receiver']) ?? $transaction, $cashInPayload);
            } elseif (in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_CASH_IN], true)) {
                app(\App\Services\StandaloneInvoiceSettlement::class)
                    ->reconcileByNumber((string) $transaction->invoice, Auth::user());
            }

            return $transaction->fresh(['details', 'sender', 'receiver']);
        });
    }

    private function validateStock(int $type, Addrbook $sender, array $items): void
    {
        if (! Transaction::typeHasItems($type)) {
            return;
        }

        $senderType = (int) $sender->type;
        if (! Addrbook::typeIsWarehouse($senderType)) {
            return;
        }
        if (Addrbook::typeAllowsNegativeStock($senderType)) {
            return;
        }

        $insufficient = [];
        foreach ($items as $item) {
            $wi = WarehouseItem::where('warehouse_id', $sender->id)->where('item_id', $item['item_id'])->first();
            $available = $wi ? (float) $wi->quantity : 0;
            if ((float) $item['quantity'] > $available) {
                $itemModel = \App\Models\Item::find($item['item_id']);
                $insufficient[] = ($itemModel ? $itemModel->name : 'ID: '.$item['item_id'])." (avail: {$available})";
            }
        }
        if (! empty($insufficient)) {
            throw ValidationException::withMessages(['items' => ['Insufficient stock for: '.implode(', ', $insufficient)]]);
        }
    }

    private function createTransaction(int $type, array $data, Addrbook $sender, Addrbook $receiver): Transaction
    {
        $trx = Transaction::create([
            'date' => $data['date'], 'type' => $type,
            'due' => $data['due'] ?? null,
            'sender_type' => (int) $sender->type,
            'sender_id' => $sender->id,
            'receiver_type' => (int) $receiver->type,
            'receiver_id' => $receiver->id,
            'notes' => $data['note'] ?? null, 'user_id' => Auth::id(),
            'status' => Transaction::STATUS_COMPLETED,
            'real_total' => 0, 'total_items' => 0,
            'adjustment' => $data['adjustment'] ?? 0,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'invoice' => ! empty($data['invoice']) ? $data['invoice'] : null,
        ]);
        if (empty($trx->invoice)) {
            $trx->update(['invoice' => (string) $trx->id]);
        }

        return $trx;
    }

    private function createDetails(Transaction $transaction, array $data): void
    {
        foreach ($data['items'] as $item) {
            $total = $this->calculateItemTotal((float) $item['quantity'], (float) $item['price'], (float) ($item['discount'] ?? 0));
            $transaction->details()->create([
                'item_id' => $item['item_id'], 'date' => $transaction->date,
                'transaction_type' => (int) $transaction->type,
                'sender_id' => $transaction->sender_id,
                'receiver_id' => $transaction->receiver_id,
                'quantity' => $item['quantity'], 'price' => $item['price'],
                'discount' => $item['discount'] ?? 0, 'total' => $total, 'notes' => $item['note'] ?? null,
            ]);
        }
    }

    private function calculateAndSetTotals(Transaction $transaction, int $type, array $data, Addrbook $sender, Addrbook $receiver): void
    {
        $transaction->refresh();
        $itemsTotal = (float) $transaction->details()->sum('total');
        $totalItems = (float) $transaction->details()->sum('quantity');
        if ($type === Transaction::TYPE_MOVE) {
            $transaction->update([
                'total' => $itemsTotal,
                'real_total' => $itemsTotal,
                'discount' => 0,
                'adjustment' => 0,
                'ppn' => 0,
                'total_items' => $totalItems,
            ]);

            return;
        }

        $discountPercent = (float) ($data['discount_percent'] ?? 0);
        $adjustment = (float) ($data['adjustment'] ?? 0);
        $isPpn = $this->shouldApplyPpn($type, $sender->id, $receiver->id);
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $totalBeforeTax = $itemsTotal - $discountAmount + $adjustment;
        $taxAmount = $isPpn ? ($totalBeforeTax * $this->getPpnRate()) : 0;
        $grandTotal = $totalBeforeTax + $taxAmount;
        $transaction->update([
            'total' => Transaction::signedAmount($type, $itemsTotal),
            'discount' => $discountPercent,
            'adjustment' => $adjustment,
            'ppn' => $taxAmount,
            'real_total' => Transaction::signedAmount($type, $grandTotal),
            'total_items' => $totalItems,
        ]);
    }
}
