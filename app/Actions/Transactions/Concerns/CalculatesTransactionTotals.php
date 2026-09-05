<?php

namespace App\Actions\Transactions\Concerns;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\PpnAmounts;

trait CalculatesTransactionTotals
{
    protected function getPpnRate(): float
    {
        return (float) Setting::getValue('ppn_rate', 11) / 100;
    }

    protected function getPpnRatePercent(): float
    {
        return (float) Setting::getValue('ppn_rate', 11);
    }

    protected function shouldApplyPpn(int $type, int $senderId, int $receiverId): bool
    {
        $addrbook = match ($type) {
            Transaction::TYPE_BUY => Addrbook::find($senderId),
            Transaction::TYPE_SELL => Addrbook::find($receiverId),
            Transaction::TYPE_RETURN => Addrbook::find($senderId),
            Transaction::TYPE_RETURN_SUPPLIER => Addrbook::find($receiverId),
            default => null,
        };

        return $addrbook && $addrbook->ppn;
    }

    protected function calculateItemTotal(float $quantity, float $price, float $discountPercent = 0): float
    {
        $gross = $quantity * $price;
        $percent = max(0.0, min(100.0, $discountPercent));

        return $gross - ($gross * $percent / 100);
    }

    protected function calculateDiscountAmount(float $grandTotal, float $discountPercent): float
    {
        return $grandTotal * ($discountPercent / 100);
    }

    protected function calculateGrandTotal(
        float $itemsTotal, float $discountPercent, float $adjustment,
        bool $isPpn, ?int $type = null, bool $ppnIncluded = false
    ): float {
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $afterDiscount = $itemsTotal - $discountAmount;
        $totalBeforeTax = $afterDiscount + $adjustment;

        if ($isPpn && $ppnIncluded) {
            $amounts = PpnAmounts::fromGross($totalBeforeTax, $this->getPpnRatePercent());
            $finalTotal = $totalBeforeTax;
        } else {
            $taxAmount = $isPpn ? ($totalBeforeTax * $this->getPpnRate()) : 0;
            $finalTotal = $totalBeforeTax + $taxAmount;
        }

        if ($type !== null && Transaction::typeIsNegative($type)) {
            return -abs($finalTotal);
        }

        return $finalTotal;
    }

    /**
     * @return array{total_before_tax: float, tax_amount: float, grand_total: float}
     */
    protected function calculateTaxTotals(
        float $itemsTotal,
        float $discountPercent,
        float $adjustment,
        bool $isPpn,
        bool $ppnIncluded = false,
    ): array {
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $totalBeforeTax = $itemsTotal - $discountAmount + $adjustment;

        if (! $isPpn) {
            return [
                'total_before_tax' => $totalBeforeTax,
                'tax_amount' => 0.0,
                'grand_total' => $totalBeforeTax,
            ];
        }

        if ($ppnIncluded) {
            $amounts = PpnAmounts::fromGross($totalBeforeTax, $this->getPpnRatePercent());

            return [
                'total_before_tax' => $amounts['dpp'],
                'tax_amount' => $amounts['ppn'],
                'grand_total' => $totalBeforeTax,
            ];
        }

        $taxAmount = $totalBeforeTax * $this->getPpnRate();

        return [
            'total_before_tax' => $totalBeforeTax,
            'tax_amount' => $taxAmount,
            'grand_total' => $totalBeforeTax + $taxAmount,
        ];
    }
}
