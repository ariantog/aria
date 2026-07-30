<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\CalculatesTransactionTotals;
use App\Enums\AddrbookType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
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

        return DB::transaction(function () use ($type, $data, $sender, $receiver) {
            $this->validateStock($type, $sender, $data['items']);
            $transaction = $this->createTransaction($type, $data, $sender, $receiver);
            $this->createDetails($transaction, $data);
            $this->calculateAndSetTotals($transaction, $type, $data, $sender, $receiver);
            $this->transactionService->handleTransaction($transaction);
            return $transaction->fresh(['details', 'sender', 'receiver']);
        });
    }

    private function validateStock(TransactionType $type, Addrbook $sender, array $items): void
    {
        if (! $type->hasItems()) return;
        $senderType = $sender->type instanceof AddrbookType ? $sender->type : AddrbookType::from($sender->type);
        if ($senderType->allowsNegativeStock()) return;

        $insufficient = [];
        foreach ($items as $item) {
            $wi = WarehouseItem::where('warehouse_id', $sender->id)->where('item_id', $item['item_id'])->first();
            $available = $wi ? (float) $wi->quantity : 0;
            if ((float) $item['quantity'] > $available) {
                $itemModel = \App\Models\Item::find($item['item_id']);
                $insufficient[] = ($itemModel ? $itemModel->name : 'ID: '.$item['item_id'])." (avail: {$available})";
            }
        }
        if (! empty($insufficient)) throw ValidationException::withMessages(['items' => ['Insufficient stock for: '.implode(', ', $insufficient)]]);
    }

    private function createTransaction(TransactionType $type, array $data, Addrbook $sender, Addrbook $receiver): Transaction
    {
        $trx = Transaction::create([
            'date' => $data['date'], 'type' => $type->value,
            'sender_type' => $sender->type instanceof AddrbookType ? $sender->type->value : $sender->type,
            'sender_id' => $sender->id,
            'receiver_type' => $receiver->type instanceof AddrbookType ? $receiver->type->value : $receiver->type,
            'receiver_id' => $receiver->id,
            'notes' => $data['note'] ?? null, 'user_id' => Auth::id(),
            'status' => TransactionStatus::Completed->value,
            'grand_total' => 0, 'total_items' => 0,
            'adjustment' => $data['adjustment'] ?? 0,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        ]);
        if (empty($trx->invoice_number)) $trx->update(['invoice_number' => (string) $trx->id]);
        return $trx;
    }

    private function createDetails(Transaction $transaction, array $data): void
    {
        foreach ($data['items'] as $item) {
            $total = $this->calculateItemTotal((float) $item['quantity'], (float) $item['price'], (float) ($item['discount'] ?? 0));
            $transaction->details()->create([
                'item_id' => $item['item_id'], 'date' => $transaction->date,
                'transaction_type' => $transaction->type, 'sender_id' => $transaction->sender_id,
                'receiver_id' => $transaction->receiver_id,
                'quantity' => $item['quantity'], 'price' => $item['price'],
                'discount' => $item['discount'] ?? 0, 'total' => $total, 'notes' => $item['note'] ?? null,
            ]);
        }
    }

    private function calculateAndSetTotals(Transaction $transaction, TransactionType $type, array $data, Addrbook $sender, Addrbook $receiver): void
    {
        $transaction->refresh();
        $itemsTotal = (float) $transaction->details()->sum('total');
        $totalItems = (float) $transaction->details()->sum('quantity');
        $discountPercent = (float) ($data['discount_percent'] ?? 0);
        $adjustment = (float) ($data['adjustment'] ?? 0);
        $isPpn = $this->shouldApplyPpn($type, $sender->id, $receiver->id);
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $totalBeforeTax = $itemsTotal - $discountAmount + $adjustment;
        $taxAmount = $isPpn ? ($totalBeforeTax * $this->getPpnRate()) : 0;
        $grandTotal = $totalBeforeTax + $taxAmount;
        if ($type->isNegative()) $grandTotal = -abs($grandTotal);
        $transaction->update([
            'total' => $itemsTotal, 'discount' => $discountAmount,
            'discount_percent' => $discountPercent, 'adjustment' => $adjustment,
            'tax_amount' => $taxAmount, 'grand_total' => $grandTotal, 'total_items' => $totalItems,
        ]);
    }
}
