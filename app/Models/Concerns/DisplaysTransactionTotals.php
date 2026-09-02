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
     * Invoice-level discount percent stored on `transactions.discount`
     * (production column is decimal(5,2) — a percent, not a money amount).
     */
    public function invoiceDiscountPercent(): float
    {
        return max(0.0, min(100.0, (float) $this->discount));
    }

    /** Positive invoice-discount money amount (percent of line subtotal). */
    public function displayInvoiceDiscountAmount(): float
    {
        return round($this->itemsSubtotalAmount() * ($this->invoiceDiscountPercent() / 100), 2);
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

    /** Signed net payable for on-screen totals (sell stays negative). */
    public function displaySignedGrandTotal(): float
    {
        return Transaction::signedAmount((int) $this->type, $this->displayGrandTotal());
    }
}
