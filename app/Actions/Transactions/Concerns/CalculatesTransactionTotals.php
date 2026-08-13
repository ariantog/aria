<?php

namespace App\Actions\Transactions\Concerns;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\Transaction;

trait CalculatesTransactionTotals
{
    protected function getPpnRate(): float
    {
        return (float) Setting::getValue('ppn_rate', 11) / 100;
    }

    protected function shouldApplyPpn(int $type, int $senderId, int $receiverId): bool
    {
        if ($type === Transaction::TYPE_BUY) {
            $addrbook = Addrbook::find($senderId);

            return $addrbook && $addrbook->ppn;
        }
        if ($type === Transaction::TYPE_SELL) {
            $addrbook = Addrbook::find($receiverId);

            return $addrbook && $addrbook->ppn;
        }

        return false;
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
        bool $isPpn, ?int $type = null
    ): float {
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $afterDiscount = $itemsTotal - $discountAmount;
        $totalBeforeTax = $afterDiscount + $adjustment;
        $taxAmount = $isPpn ? ($totalBeforeTax * $this->getPpnRate()) : 0;
        $finalTotal = $totalBeforeTax + $taxAmount;
        if ($type !== null && Transaction::typeIsNegative($type)) {
            return -abs($finalTotal);
        }

        return $finalTotal;
    }
}
