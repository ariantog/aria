<?php

namespace App\Support;

class AmountFormatter
{
    public const DECIMAL_SEPARATOR = '.';

    public const THOUSANDS_SEPARATOR = ',';

    /**
     * Format a numeric amount with comma thousands grouping and up to two decimal places.
     * Trailing zeros after the decimal separator are removed (e.g. 2.00 → 2).
     */
    public static function format(float|int|string|null $value, int $maxDecimals = 2): string
    {
        $formatted = number_format(
            (float) ($value ?? 0),
            $maxDecimals,
            self::DECIMAL_SEPARATOR,
            self::THOUSANDS_SEPARATOR,
        );

        if ($maxDecimals === 0 || ! str_contains($formatted, self::DECIMAL_SEPARATOR)) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), self::DECIMAL_SEPARATOR);
    }

    public static function currency(float|int|string|null $value, string $prefix = 'Rp ', int $maxDecimals = 2): string
    {
        return $prefix.self::format($value, $maxDecimals);
    }

    /**
     * Format a numeric amount without currency prefix or thousands grouping.
     * Used when copying table cells into a spreadsheet.
     */
    public static function plain(float|int|string|null $value, int $maxDecimals = 2): string
    {
        $formatted = number_format(
            (float) ($value ?? 0),
            $maxDecimals,
            self::DECIMAL_SEPARATOR,
            '',
        );

        if ($maxDecimals === 0 || ! str_contains($formatted, self::DECIMAL_SEPARATOR)) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), self::DECIMAL_SEPARATOR);
    }

    /**
     * Tailwind text-size classes for displaying formatted IDR amounts without overflow.
     *
     * @param  'hero'|'compact'  $scale
     */
    public static function displayTextClass(string $formatted, string $scale = 'hero'): string
    {
        $len = strlen($formatted);

        if ($scale === 'compact') {
            return match (true) {
                $len > 14 => 'text-xs font-bold leading-tight sm:text-sm',
                $len > 10 => 'text-sm font-bold leading-tight sm:text-base',
                $len > 7 => 'text-base font-bold leading-tight sm:text-lg',
                default => 'text-lg font-bold leading-tight sm:text-xl',
            };
        }

        return match (true) {
            $len > 14 => 'text-sm font-bold leading-tight sm:text-base',
            $len > 10 => 'text-base font-bold leading-tight sm:text-lg',
            $len > 7 => 'text-lg font-bold leading-tight sm:text-xl',
            default => 'text-xl font-bold leading-tight sm:text-2xl',
        };
    }
}
