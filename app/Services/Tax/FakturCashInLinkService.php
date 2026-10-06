<?php

namespace App\Services\Tax;

use App\Models\TaxFakturImport;
use App\Models\Transaction;

class FakturCashInLinkService
{
    /**
     * When a linked Cash In is deleted, clear payment linkage on the faktur import
     * so the faktur can be paid / linked again.
     */
    public function unlinkDeletedCashIn(Transaction $cashIn): void
    {
        if ((int) $cashIn->type !== Transaction::TYPE_CASH_IN) {
            return;
        }

        TaxFakturImport::query()
            ->where('cash_in_transaction_id', $cashIn->id)
            ->each(fn (TaxFakturImport $import) => $this->clearPaymentLink($import));
    }

    /**
     * Manual unlink from faktur detail (does not delete the Cash In transaction).
     */
    public function clearPaymentLink(TaxFakturImport $import): TaxFakturImport
    {
        $import->cash_in_transaction_id = null;
        $import->payment_received_amount = null;
        $import->payment_received_date = null;
        $import->payment_variance = null;
        $import->variance_transaction_id = null;
        $import->save();

        return $import->fresh();
    }

    public function hasLiveCashInLink(TaxFakturImport $import): bool
    {
        if (! $import->cash_in_transaction_id) {
            return false;
        }

        return Transaction::query()
            ->whereKey($import->cash_in_transaction_id)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->exists();
    }
}
