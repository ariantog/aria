<?php

namespace App\Support;

/**
 * Derive a marketing title from legacy `item_group.alias` when parent `product_name` is empty.
 *
 * Rules: only when alias contains `-` or `/`, take the substring before the first delimiter;
 * that substring must be non-empty and must contain no ASCII digits; result is uppercased.
 */
final class ItemGroupAliasMarketingTitle
{
    public static function extract(?string $alias): ?string
    {
        $raw = trim((string) $alias);
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, '-') && ! str_contains($raw, '/')) {
            return null;
        }

        $parts = preg_split('/[-\/]/', $raw, 2);
        $candidate = isset($parts[0]) ? trim($parts[0]) : '';

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/[0-9]/', $candidate) === 1) {
            return null;
        }

        return strtoupper($candidate);
    }
}
