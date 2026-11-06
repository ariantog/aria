<?php

namespace App\Support;

/**
 * Fallback marketing title from `item_group.name` when parent `product_name` is empty.
 */
final class ItemGroupMarketingTitleFallback
{
    public static function fromGroupName(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (ItemParentTitleEligibility::looksLikePcodeOrSegment($raw)) {
            return null;
        }

        return strtoupper($raw);
    }
}
