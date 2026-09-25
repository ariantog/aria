<?php

namespace App\Support;

/**
 * Catalog cascade: parent group → colorway (item_group) → SKU (items).
 *
 * @see doc/item-catalog-hierarchy.md
 */
final class ItemCatalogHierarchy
{
    public const SCOPE_SIZE = ItemPricing::SCOPE_SIZE;

    public const SCOPE_COLORWAY = ItemPricing::SCOPE_COLORWAY;

    public const SCOPE_GROUP = ItemPricing::SCOPE_GROUP;

    /** @var list<string> */
    public const PRICING_FIELDS = ItemPricing::FIELDS;

    /** @var list<string> */
    public const TEXT_FIELDS = [
        'description',
        'description2',
    ];

    /** @var list<string> */
    public const TITLE_FIELDS = [
        'product_name',
        'alias',
    ];

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [
            self::SCOPE_SIZE,
            self::SCOPE_COLORWAY,
            self::SCOPE_GROUP,
        ];
    }
}
