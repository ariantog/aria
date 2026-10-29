<?php

namespace App\Services\Readonly;

final class ReadonlyCostFlagResolver
{
    private const PLACEHOLDER_MAX_RUPIAH = 1000.0;

    private const PLACEHOLDER_PRICE_RATIO = 0.10;

    public function flag(float $baseCost, float $price): string
    {
        if ($baseCost <= 0) {
            return 'missing';
        }

        if ($baseCost <= self::PLACEHOLDER_MAX_RUPIAH) {
            return 'placeholder';
        }

        if ($price > 0 && ($baseCost / $price) < self::PLACEHOLDER_PRICE_RATIO) {
            return 'placeholder';
        }

        if ($price > 0 && $baseCost >= $price) {
            return 'above_price';
        }

        return 'ok';
    }
}
