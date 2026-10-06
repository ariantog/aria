<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\FakturPaymentTotals;
use App\Support\VarianceCashTaxAmounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostFakturPaymentVariance
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * Post consignment / fee selisih as Adjust: counterparty → expense ledger.
     * Does not move bank cash (underpayment vs faktur gross is not a second bank outflow).
     */
    public function execute(TaxFakturImport $import): ?Transaction
    {
        if ($import->variance_transaction_id) {
            return $import->varianceTransaction;
        }

        if (! $import->variance_expense_addrbook_id) {
            return null;
        }

        $bankAmount = FakturPaymentTotals::bankAmountFromCashIn(
            $import->cashInTransaction ?? ($import->cash_in_transaction_id
                ? Transaction::query()->find($import->cash_in_transaction_id)
                : null),
        );

        $selisih = $bankAmount !== null
            ? FakturPaymentTotals::selisihAmount(
                $import,
                $bankAmount,
                (int) $import->variance_expense_addrbook_id,
            )
            : null;

        if ($selisih === null || $selisih < 0.01) {
            $variance = $import->payment_variance !== null ? (float) $import->payment_variance : null;
            if ($variance === null || $variance >= -0.01) {
                return null;
            }
            $selisih = abs($variance);
        }

        $expenseAccount = Addrbook::query()->find($import->variance_expense_addrbook_id);
        if (! $expenseAccount || (int) $expenseAccount->type !== Addrbook::TYPE_ACCOUNT) {
            throw new InvalidArgumentException('Variance expense account must be a ledger account.');
        }

        $counterparty = $this->resolveCounterparty($import);

        $amount = $selisih;
        $date = $import->payment_received_date?->toDateString() ?? now()->toDateString();
        $grandTotal = Transaction::signedAmount(Transaction::TYPE_ADJUST, $amount);
        $tax = VarianceCashTaxAmounts::resolve($amount, VarianceCashTaxAmounts::inputFromImport($import));

        return DB::transaction(function () use ($import, $counterparty, $expenseAccount, $date, $grandTotal, $amount, $tax) {
            $transaction = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_ADJUST,
                'sender_type' => (int) $counterparty->type,
                'sender_id' => $counterparty->id,
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
                'ppn' => $tax['ppn'],
                'ppn_dpp' => $tax['ppn_dpp'],
                'pph' => $tax['pph'],
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

    private function resolveCounterparty(TaxFakturImport $import): Addrbook
    {
        $import->loadMissing('counterparty');
        $party = $import->counterparty;
        if (! $party && $import->counterparty_id) {
            $party = Addrbook::query()->find($import->counterparty_id);
        }

        if (! $party || ! in_array((int) $party->type, Addrbook::cashPartyTypes(), true)) {
            throw new InvalidArgumentException('Cannot post variance without a faktur counterparty (customer / reseller / supplier).');
        }

        return $party;
    }
}
