<?php

if (! function_exists('format_amount')) {
    /**
     * Format a numeric amount with Indonesian grouping and up to two decimal places.
     * Trailing zeros after the decimal separator are removed (e.g. 2,00 → 2).
     */
    function format_amount(float|int|string|null $value, int $maxDecimals = 2): string
    {
        $formatted = number_format((float) ($value ?? 0), $maxDecimals, ',', '.');

        if (! str_contains($formatted, ',')) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (! function_exists('format_currency')) {
    function format_currency(float|int|string|null $value, string $prefix = 'Rp '): string
    {
        return $prefix.format_amount($value);
    }
}
