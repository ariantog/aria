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
            ->each(function (TaxFakturImport $import): void {
                $import->cash_in_transaction_id = null;
                $import->payment_received_amount = null;
                $import->payment_received_date = null;
                $import->payment_variance = null;
                $import->variance_transaction_id = null;
                $import->save();
            });
    }
}
