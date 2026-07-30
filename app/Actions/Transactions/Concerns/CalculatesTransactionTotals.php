<?php

namespace App\Actions\Transactions\Concerns;

use App\Enums\TransactionType;
use App\Models\Addrbook;
use App\Models\Setting;

trait CalculatesTransactionTotals
{
    protected function getPpnRate(): float
    {
        return (float) Setting::getValue('ppn_rate', 11) / 100;
    }

    protected function shouldApplyPpn(TransactionType $type, int $senderId, int $receiverId): bool
    {
        if ($type === TransactionType::Buy) {
            $addrbook = Addrbook::find($senderId);
            return $addrbook && $addrbook->ppn;
        }
        if ($type === TransactionType::Sell) {
            $addrbook = Addrbook::find($receiverId);
            return $addrbook && $addrbook->ppn;
        }
        return false;
    }

    protected function calculateItemTotal(float $quantity, float $price, float $discount = 0): float
    {
        return ($quantity * $price) - $discount;
    }

    protected function calculateDiscountAmount(float $grandTotal, float $discountPercent): float
    {
        return $grandTotal * ($discountPercent / 100);
    }

    protected function calculateGrandTotal(
        float $itemsTotal, float $discountPercent, float $adjustment,
        bool $isPpn, ?TransactionType $type = null
    ): float {
        $discountAmount = $this->calculateDiscountAmount($itemsTotal, $discountPercent);
        $afterDiscount = $itemsTotal - $discountAmount;
        $totalBeforeTax = $afterDiscount + $adjustment;
        $taxAmount = $isPpn ? ($totalBeforeTax * $this->getPpnRate()) : 0;
        $finalTotal = $totalBeforeTax + $taxAmount;
        if ($type && $type->isNegative()) return -abs($finalTotal);
        return $finalTotal;
    }
}
