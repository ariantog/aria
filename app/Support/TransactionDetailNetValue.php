<?php

namespace App\Support;

/**
 * Net revenue attributed to a sell/return line for warehouse stats and item analytics.
 *
 * Line subtotal after invoice discount, plus an equal share of the header adjustment.
 */
final class TransactionDetailNetValue
{
    public static function headerDiscountPercent(mixed $discount): float
    {
        return max(0.0, min(100.0, (float) ($discount ?? 0)));
    }

    public static function lineTotalAfterDiscount(float $lineTotal, float $headerDiscountPercent): float
    {
        return $lineTotal * (100 - $headerDiscountPercent) / 100;
    }

    public static function adjustmentShare(float $adjustment, int $lineCount): float
    {
        if ($lineCount <= 0 || abs($adjustment) < 0.00001) {
            return 0.0;
        }

        return $adjustment / $lineCount;
    }

    public static function net(
        float $lineTotal,
        mixed $headerDiscount,
        mixed $adjustment,
        int $lineCount,
    ): float {
        $afterDiscount = self::lineTotalAfterDiscount(
            $lineTotal,
            self::headerDiscountPercent($headerDiscount),
        );

        return $afterDiscount + self::adjustmentShare((float) ($adjustment ?? 0), $lineCount);
    }
}
