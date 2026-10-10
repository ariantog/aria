<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\Items\ItemIdentityBuilder;

/**
 * Stored vs effective bare product titles for catalog edit forms (parent → colorway → SKU).
 *
 * @see doc/item-catalog-hierarchy.md
 */
final class ItemCatalogTitleForm
{
    /**
     * @return array{
     *     stored_title: string,
     *     effective_title: string,
     *     parent_title: string,
     *     uses_placeholder: bool,
     *     inherits_parent: bool,
     * }
     */
    public static function forItem(Item $item, ItemIdentityBuilder $builder): array
    {
        $item->loadMissing(['group', 'tags']);
        $itemType = $item->type instanceof ItemType ? $item->type : ItemType::coerce($item->type) ?? ItemType::ITEM;
        $pcode = strtoupper(trim((string) $item->pcode));
        $group = $item->group;
        $parentTitle = ItemProductTitle::parentProductNameForKey($builder->itemParentKey($item));
        $effectiveTitle = ItemProductTitle::resolveBareTitle($item);

        $storedRaw = trim((string) ($group?->name ?? ''));
        $usesPlaceholder = self::groupNameIsPlaceholder($itemType, $storedRaw, $pcode, $group, $builder);

        $storedTitle = '';
        if ($storedRaw !== '' && ! $usesPlaceholder) {
            $storedTitle = $builder->productDisplayName(
                $itemType,
                $storedRaw,
                (string) ($group?->variant ?? ''),
                (string) ($group?->master ?? ''),
            );
        }

        $inheritsParent = $usesPlaceholder
            && $parentTitle !== ''
            && strtoupper($effectiveTitle) === strtoupper($parentTitle);

        return [
            'stored_title' => $storedTitle,
            'effective_title' => $effectiveTitle,
            'parent_title' => $parentTitle,
            'uses_placeholder' => $usesPlaceholder,
            'inherits_parent' => $inheritsParent,
        ];
    }

    public static function groupNameIsPlaceholder(
        ItemType $type,
        string $storedGroupName,
        string $pcode,
        ?ItemGroup $group,
        ItemIdentityBuilder $builder,
    ): bool {
        $storedGroupName = trim($storedGroupName);
        if ($storedGroupName === '') {
            return true;
        }

        if (ItemProductTitle::isPcodePlaceholderName($type, $storedGroupName, $pcode)) {
            return true;
        }

        if ($type === ItemType::ITEM) {
            $normalized = $builder->normalizeManufacturedPcode($pcode);

            return strtoupper($storedGroupName) === strtoupper($normalized);
        }

        return false;
    }
}
