<?php

namespace App\Actions\Transactions;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Addrbook;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateTransferTransaction
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function execute(StoreTransferRequest $request): Transaction
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data) {
            $sender = Addrbook::findOrFail($data['sender']);
            $receiver = Addrbook::findOrFail($data['receiver']);

            $trx = Transaction::create([
                'date' => $data['date'],
                'type' => Transaction::TYPE_TRANSFER,
                'sender_type' => (int) $sender->type,
                'sender_id' => $sender->id,
                'receiver_type' => (int) $receiver->type,
                'receiver_id' => $receiver->id,
                'invoice' => $data['invoice'] ?? null,
                'notes' => $data['description'] ?? null,
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => (float) $data['total'],
                'real_total' => (float) $data['total'],
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            if (empty($trx->invoice)) {
                $trx->update(['invoice' => (string) $trx->id]);
            }

            $this->transactionService->handleTransaction($trx);

            return $trx->fresh();
        });
    }
}
