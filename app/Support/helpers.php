<?php

use App\Support\AmountFormatter;

if (! function_exists('format_amount')) {
    function format_amount(float|int|string|null $value, int $maxDecimals = 2): string
    {
        return AmountFormatter::format($value, $maxDecimals);
    }
}

if (! function_exists('format_number')) {
    function format_number(float|int|string|null $value, int $maxDecimals = 0): string
    {
        return AmountFormatter::format($value, $maxDecimals);
    }
}

if (! function_exists('format_currency')) {
    function format_currency(float|int|string|null $value, string $prefix = 'Rp ', int $maxDecimals = 2): string
    {
        return AmountFormatter::currency($value, $prefix, $maxDecimals);
    }
}

if (! function_exists('format_table_amount')) {
    function format_table_amount(float|int|string|null $value, int $decimals = 2): string
    {
        return AmountFormatter::formatTable($value, $decimals);
    }
}

if (! function_exists('format_table_currency')) {
    function format_table_currency(float|int|string|null $value, string $prefix = 'Rp ', int $decimals = 2): string
    {
        return AmountFormatter::tableCurrency($value, $prefix, $decimals);
    }
}

if (! function_exists('format_copy_number')) {
    function format_copy_number(float|int|string|null $value, int $maxDecimals = 2): string
    {
        return AmountFormatter::plain($value, $maxDecimals);
    }
}
