<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostFakturPaymentVariance
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * Post payment variance as a Cash Out to the expense ledger (e.g. biaya MDS).
     * Only negative variance (underpayment / fees) is posted automatically.
     */
    public function execute(TaxFakturImport $import): ?Transaction
    {
        if ($import->variance_transaction_id) {
            return $import->varianceTransaction;
        }

        $variance = $import->payment_variance !== null ? (float) $import->payment_variance : null;
        if ($variance === null || abs($variance) < 0.01) {
            return null;
        }

        if (! $import->variance_expense_addrbook_id) {
            return null;
        }

        if ($variance > 0) {
            return null;
        }

        $expenseAccount = Addrbook::query()->find($import->variance_expense_addrbook_id);
        if (! $expenseAccount || (int) $expenseAccount->type !== Addrbook::TYPE_ACCOUNT) {
            throw new InvalidArgumentException('Variance expense account must be a ledger account.');
        }

        $bankId = $this->resolveBankId($import);
        if (! $bankId) {
            throw new InvalidArgumentException('Cannot post variance without a bank on the linked Cash In or reporting entity.');
        }

        $amount = abs($variance);
        $date = $import->payment_received_date?->toDateString() ?? now()->toDateString();
        $grandTotal = Transaction::signedAmount(Transaction::TYPE_CASH_OUT, $amount);

        return DB::transaction(function () use ($import, $bankId, $expenseAccount, $date, $grandTotal, $amount) {
            $transaction = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_CASH_OUT,
                'sender_type' => Addrbook::TYPE_BANK,
                'sender_id' => $bankId,
                'receiver_type' => Addrbook::TYPE_ACCOUNT,
                'receiver_id' => $expenseAccount->id,
                'invoice' => $import->faktur_number,
                'notes' => sprintf('Selisih pembayaran faktur %s (Rp %s)', $import->faktur_number, number_format($amount, 2, '.', '')),
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => $grandTotal,
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            if (empty($transaction->invoice)) {
                $transaction->update(['invoice' => (string) $transaction->id]);
            }

            $this->transactionService->handleTransaction($transaction);

            $import->variance_transaction_id = $transaction->id;
            $import->save();

            return $transaction;
        });
    }

    private function resolveBankId(TaxFakturImport $import): ?int
    {
        if ($import->cash_in_transaction_id) {
            $cashIn = Transaction::query()->find($import->cash_in_transaction_id);
            if ($cashIn && (int) $cashIn->receiver_type === Addrbook::TYPE_BANK) {
                return (int) $cashIn->receiver_id;
            }
        }

        $entity = $import->reportingEntity;
        if (! $entity) {
            $entity = ReportingEntity::query()->find($import->reporting_entity_id);
        }

        $bank = $entity?->banks()
            ->wherePivot('is_active', true)
            ->orderBy('customers.id')
            ->first();

        return $bank ? (int) $bank->id : null;
    }
}
