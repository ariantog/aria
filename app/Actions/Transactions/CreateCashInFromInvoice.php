<?php

namespace App\Actions\Transactions;

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Models\Transaction;
use App\Services\StandaloneInvoiceSettlement;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCashInFromInvoice
{
    public function __construct(private readonly TransactionService $transactionService) {}

    /**
     * @param  array{date?: string|null, account_id: int, amount: float, sender_id: int}  $data
     */
    public function execute(StandaloneInvoice $invoice, array $data): Transaction
    {
        $contact = Addrbook::query()->find((int) $data['sender_id']);
        if (! $contact || ! in_array((int) $contact->type, Addrbook::cashPartyTypes(), true)) {
            throw ValidationException::withMessages([
                'sender_id' => ['Select a customer, reseller, supplier, or ledger account.'],
            ]);
        }

        $account = Addrbook::findOrFail((int) $data['account_id']);
        if ((int) $account->type !== Addrbook::TYPE_BANK) {
            throw ValidationException::withMessages([
                'account_id' => ['Select a bank account.'],
            ]);
        }

        $amount = (float) $data['amount'];
        if ($amount < 0.01) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be at least 0.01.'],
            ]);
        }

        $date = ! empty($data['date'])
            ? Carbon::parse((string) $data['date'])->toDateString()
            : Carbon::today()->toDateString();
        $grandTotal = Transaction::signedAmount(Transaction::TYPE_CASH_IN, $amount);

        return DB::transaction(function () use ($invoice, $contact, $account, $date, $grandTotal) {
            $trx = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_CASH_IN,
                'sender_type' => (int) $contact->type,
                'sender_id' => $contact->id,
                'receiver_type' => (int) $account->type,
                'receiver_id' => $account->id,
                'invoice' => $invoice->number,
                'notes' => null,
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => $grandTotal,
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'ppn_dpp' => null,
                'pph' => null,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            $this->transactionService->handleTransaction($trx);
            app(StandaloneInvoiceSettlement::class)
                ->reconcile($invoice->fresh() ?? $invoice, Auth::user());

            return $trx->fresh(['sender', 'receiver']) ?? $trx;
        });
    }
}
