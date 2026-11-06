<?php

namespace App\Support;

use App\Models\Item;

/**
 * Build display `items.name` from a parent catalog title + SKU tags (warna/size).
 * Optionally sync `items.alias` to the parent title when that column exists.
 *
 * @see doc/item-catalog-hierarchy.md
 */
final class ItemParentDisplayNameBackfill
{
    /**
     * @return array{
     *     newName: string,
     *     nameChanged: bool,
     *     aliasChanged: bool,
     *     currentAlias: string,
     *     normalizedDesiredAlias: string,
     *     hasAliasColumn: bool
     * }
     */
    public static function compute(Item $item, string $parentTitle, bool $hasAliasColumn): array
    {
        $item->loadMissing(['group', 'tags']);

        $oldNameUpper = strtoupper(trim((string) $item->name));

        $currentAlias = '';
        $normalizedDesired = '';
        $aliasChanged = false;

        if ($hasAliasColumn) {
            $desiredAlias = $parentTitle;
            $currentAlias = strtoupper(trim((string) ($item->alias ?? '')));
            $normalizedDesired = strtoupper(trim($desiredAlias));
            $item->alias = $desiredAlias;
            $aliasChanged = $currentAlias !== $normalizedDesired;
        }

        $newName = ItemProductTitle::buildDisplayNameWithBareTitle($item, $parentTitle);
        $nameChanged = $oldNameUpper !== strtoupper(trim($newName));

        return [
            'newName' => $newName,
            'nameChanged' => $nameChanged,
            'aliasChanged' => $aliasChanged,
            'currentAlias' => $currentAlias,
            'normalizedDesiredAlias' => $normalizedDesired,
            'hasAliasColumn' => $hasAliasColumn,
        ];
    }
}
