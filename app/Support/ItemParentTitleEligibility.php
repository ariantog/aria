<?php

namespace App\Support;

/**
 * Rules for using parent catalog titles (e.g. build SKUs display names).
 *
 * Values containing `-` or `/` are treated as pcode-like codes, not human marketing titles.
 */
final class ItemParentTitleEligibility
{
    /**
     * Non-empty string that contains `-` or `/` is not used as a product title.
     */
    public static function looksLikePcodeOrSegment(string $value): bool
    {
        $v = trim($value);

        return $v !== '' && (str_contains($v, '-') || str_contains($v, '/'));
    }
}
