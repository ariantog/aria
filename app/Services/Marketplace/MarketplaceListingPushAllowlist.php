<?php

namespace App\Services\Marketplace;

use App\Models\Item;

class MarketplaceListingPushAllowlist
{
    /** @return list<int> */
    public function allowedItemIds(): array
    {
        $ids = config('marketplace.listing_push.allowlist_item_ids', []);

        return is_array($ids)
            ? array_values(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0))
            : [];
    }

    public function allowsItem(Item|int $item): bool
    {
        $allowed = $this->allowedItemIds();
        if ($allowed === []) {
            return false;
        }

        $itemId = $item instanceof Item ? (int) $item->id : (int) $item;

        return in_array($itemId, $allowed, true);
    }
}