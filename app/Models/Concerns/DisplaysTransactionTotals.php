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
     * Signed invoice-discount contribution (add to signed subtotal).
     * Discount reduces the unsigned payable, so on a sell it is positive
     * (moves the signed total toward zero).
     */
    public function displaySignedInvoiceDiscount(): float
    {
        return -Transaction::signedAmount((int) $this->type, $this->displayInvoiceDiscountAmount());
    }

    /**
     * Signed adjustment contribution (add to signed subtotal).
     * Matches the write path: unsigned payable += stored `adjustment`.
     */
    public function displaySignedAdjustment(): float
    {
        $adjustment = (float) $this->adjustment;
        if (abs($adjustment) < 0.00001) {
            return 0.0;
        }

        $signedMagnitude = Transaction::signedAmount((int) $this->type, abs($adjustment));

        return $adjustment < 0 ? -$signedMagnitude : $signedMagnitude;
    }

    /**
     * Signed stored-PPN contribution (add to signed subtotal).
     * Uses `transactions.ppn` as written — 0 when tax was not recorded.
     * Do not infer 11% from the rate or the reporting entity.
     */
    public function displaySignedPpn(): float
    {
        return Transaction::signedAmount((int) $this->type, abs((float) $this->ppn));
    }

    /**
     * Signed subtotal after invoice discount and adjustment, before PPN handling.
     */
    public function displaySignedTotalBeforePpn(): float
    {
        return round(
            $this->displaySummarySubtotal()
            + $this->displaySignedInvoiceDiscount()
            + $this->displaySignedAdjustment(),
            2,
        );
    }

    /**
     * Whether stored `total` already includes PPN (tax-inclusive / gross payable).
     *
     * Excluded writes store net + `ppn` separately. Included writes store gross on
     * `total` with `ppn` as the extracted tax component only. Faktur sells store DPP
     * on `total` with PPN separate — same reconstruction rule (do not add stored PPN).
     */
    public function storedPpnIsIncludedInPayable(): bool
    {
        if (abs((float) $this->ppn) < 0.01) {
            return false;
        }

        $beforePpn = $this->displaySignedTotalBeforePpn();
        $stored = $this->displaySignedGrandTotal();
        $withPpn = round($beforePpn + $this->displaySignedPpn(), 2);

        if (abs($beforePpn - $stored) < 0.01) {
            return true;
        }

        if (abs($withPpn - $stored) < 0.01) {
            return false;
        }

        return abs($beforePpn - $stored) < abs($withPpn - $stored);
    }

    /**
     * Recompute signed payable from lines + header discount / adjustment + stored PPN.
     * When PPN was written as tax-inclusive, `total` is already gross and stored
     * `ppn` must not be added again.
     */
    public function displayReconstructedSignedTotal(): float
    {
        $beforePpn = $this->displaySignedTotalBeforePpn();

        if ($this->storedPpnIsIncludedInPayable()) {
            return $beforePpn;
        }

        return round($beforePpn + $this->displaySignedPpn(), 2);
    }

    /**
     * True when stored `total` does not equal the reconstructed payable from lines.
     * Only checked when a header discount or adjustment is present, so faktur
     * sells (DPP on `total`, PPN stored separately) and non-tax rows (ppn = 0)
     * are not flagged. Marks leftover early-L12 writes that stored the pre-discount subtotal.
     */
    public function hasLegacyTotalMismatch(): bool
    {
        $hasHeaderMoney = $this->invoiceDiscountPercent() > 0
            || abs((float) $this->adjustment) >= 0.01;

        if (! $hasHeaderMoney) {
            return false;
        }

        $details = $this->relationLoaded('details')
            ? $this->details
            : $this->details()->get();

        if ($details->isEmpty()) {
            return false;
        }

        return abs(
            $this->displayReconstructedSignedTotal() - $this->displaySignedGrandTotal()
        ) >= 0.01;
    }

    /**
     * Positive net payable for PDF export and print (unsigned).
     * Header `total` is the only amount — do not read `real_total`.
     */
    public function displayGrandTotal(): float
    {
        return abs((float) $this->total);
    }

    /** Signed net payable for on-screen totals (sell stays negative). */
    public function displaySignedGrandTotal(): float
    {
        return Transaction::signedAmount((int) $this->type, $this->displayGrandTotal());
    }
}
