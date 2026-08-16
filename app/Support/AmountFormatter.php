<?php

namespace App\Support;

class AmountFormatter
{
    /**
     * Format a numeric amount with Indonesian grouping and up to two decimal places.
     * Trailing zeros after the decimal separator are removed (e.g. 2,00 → 2).
     */
    public static function format(float|int|string|null $value, int $maxDecimals = 2): string
    {
        $formatted = number_format((float) ($value ?? 0), $maxDecimals, ',', '.');

        if (! str_contains($formatted, ',')) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public static function currency(float|int|string|null $value, string $prefix = 'Rp '): string
    {
        return $prefix.self::format($value);
    }
}
