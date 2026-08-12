<?php

namespace App\Actions\Transactions;

use App\Enums\AddrbookType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Models\Addrbook;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateCashTransaction
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function execute(StoreCashTransactionRequest $request, bool $isCashIn): array
    {
        $data = $request->validated();
        $type = $isCashIn ? TransactionType::CashIn : TransactionType::CashOut;
        $createdIds = [];

        DB::transaction(function () use ($type, $data, $isCashIn, &$createdIds) {
            $account = Addrbook::findOrFail($data['account_id']);
            foreach ($data['items'] as $item) {
                $contact = Addrbook::findOrFail($item['customer_id']);
                $sender = $isCashIn ? $contact : $account;
                $receiver = $isCashIn ? $account : $contact;
                $total = (float) $item['total'];
                $grandTotal = $isCashIn ? $total : -$total;
                $trx = Transaction::create([
                    'date' => $data['date'], 'type' => $type->value,
                    'sender_type' => $sender->type instanceof AddrbookType ? $sender->type->value : $sender->type,
                    'sender_id' => $sender->id,
                    'receiver_type' => $receiver->type instanceof AddrbookType ? $receiver->type->value : $receiver->type,
                    'receiver_id' => $receiver->id,
                    'invoice' => $item['invoice'] ?? null,
                    'notes' => $item['note'] ?? null, 'user_id' => Auth::id(),
                    'status' => TransactionStatus::Completed->value,
                    'real_total' => $grandTotal, 'total_items' => 0,
                    'adjustment' => 0, 'discount' => 0, 'ppn' => 0,
                    'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                ]);
                if (empty($trx->invoice)) $trx->update(['invoice' => (string) $trx->id]);
                $this->transactionService->handleTransaction($trx);
                $createdIds[] = $trx->id;
            }
        });
        return $createdIds;
    }
}
