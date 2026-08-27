<?php

namespace App\Actions\Transactions;

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
        $type = $isCashIn ? Transaction::TYPE_CASH_IN : Transaction::TYPE_CASH_OUT;
        $createdIds = [];

        DB::transaction(function () use ($type, $data, $isCashIn, &$createdIds) {
            $account = Addrbook::findOrFail($data['account_id']);
            foreach ($data['items'] as $item) {
                $contact = Addrbook::findOrFail($item['customer_id']);
                $sender = $isCashIn ? $contact : $account;
                $receiver = $isCashIn ? $account : $contact;
                $total = (float) $item['total'];
                $grandTotal = Transaction::signedAmount($type, $total);
                $recordPpn = filter_var($item['record_ppn'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $ppn = $recordPpn ? (float) $item['ppn'] : 0;
                $ppnDpp = $recordPpn ? (float) $item['ppn_dpp'] : null;
                $trx = Transaction::create([
                    'date' => $data['date'], 'type' => $type,
                    'sender_type' => (int) $sender->type,
                    'sender_id' => $sender->id,
                    'receiver_type' => (int) $receiver->type,
                    'receiver_id' => $receiver->id,
                    'invoice' => $item['invoice'] ?? null,
                    'notes' => $item['note'] ?? null, 'user_id' => Auth::id(),
                    'status' => Transaction::STATUS_COMPLETED,
                    'total' => $grandTotal,
                    'real_total' => $grandTotal,
                    'total_items' => 0,
                    'adjustment' => 0, 'discount' => 0,
                    'ppn' => $ppn,
                    'ppn_dpp' => $ppnDpp,
                    'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                ]);
                if (empty($trx->invoice)) {
                    $trx->update(['invoice' => (string) $trx->id]);
                }
                $this->transactionService->handleTransaction($trx);
                $createdIds[] = $trx->id;
            }
        });

        return $createdIds;
    }
}
