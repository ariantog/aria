<?php

namespace App\Models\Concerns;

use App\Models\Transaction;

trait DisplaysTransactionTotals
{
    public function isFromJubelio(): bool
    {
        return (int) $this->submit_type === Transaction::SUBMIT_TYPE_JUBELIO;
    }

    /** Positive sum of line-item amounts (absolute). */
    public function itemsSubtotalAmount(): float
    {
        $details = $this->relationLoaded('details')
            ? $this->details
            : $this->details()->get();

        if ($details->isEmpty()) {
            if ($this->isFromJubelio()) {
                return max(abs((float) $this->real_total), abs((float) $this->total));
            }

            return abs((float) $this->total);
        }

        return (float) $details->sum(fn ($detail) => abs((float) $detail->total));
    }

    /** Signed line subtotal for summary rows. */
    public function displaySummarySubtotal(): float
    {
        return Transaction::signedAmount((int) $this->type, $this->itemsSubtotalAmount());
    }

    /**
     * Positive net payable for PDF export and print (unsigned).
     *
     * Manual Aria rows store net on `real_total` and gross line sum on `total`.
     * Jubelio rows may store subtotal/receivable on either column depending on era;
     * net is the smaller absolute header amount when they differ.
     */
    public function displayGrandTotal(): float
    {
        $real = abs((float) $this->real_total);
        $total = abs((float) $this->total);

        if (
            $this->isFromJubelio()
            && in_array((int) $this->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN], true)
        ) {
            if ($real < 0.00001 && $total < 0.00001) {
                return 0.0;
            }

            return min($real, $total);
        }

        return $real;
    }
}
