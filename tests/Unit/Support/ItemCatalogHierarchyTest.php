<?php

use App\Support\ItemCatalogHierarchy;
use App\Support\ItemPricing;

test('hierarchy scopes match pricing scopes', function () {
    expect(ItemCatalogHierarchy::scopes())->toBe([
        ItemCatalogHierarchy::SCOPE_SIZE,
        ItemCatalogHierarchy::SCOPE_COLORWAY,
        ItemCatalogHierarchy::SCOPE_GROUP,
    ])->and(ItemCatalogHierarchy::PRICING_FIELDS)->toBe(ItemPricing::FIELDS);
});
