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
     * Positive net payable for detail pages, print, and PDF.
     *
     * Legacy Jubelio cron rows sometimes stored seller income on `total` while
     * `real_total` still added `adjustment` again (|total| + |adjustment| ≈ |real_total|).
     */
    public function displayGrandTotal(): float
    {
        $real = abs((float) $this->real_total);
        $total = abs((float) $this->total);
        $adjustment = abs((float) $this->adjustment);

        if (
            $this->isFromJubelio()
            && (int) $this->type === Transaction::TYPE_SELL
            && $adjustment > 0.00001
            && abs(($total + $adjustment) - $real) < 0.01
        ) {
            return $total;
        }

        return $real;
    }
}
