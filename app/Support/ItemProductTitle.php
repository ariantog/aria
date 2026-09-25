<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Bare product title resolves: SKU (items.alias) → colorway (item_group.name) → parent group.
 *
 * Full SKU display names append warna and size via ItemIdentityBuilder::buildName().
 *
 * Legacy production may still have item_group.alias; when item_group.name is empty or
 * pcode-like, a non-empty group alias is used as the colorway title before parent fallback.
 */
final class ItemProductTitle
{
    /** @var array<string, bool> */
    private static array $columnExists = [];

    /** @var array<string, ItemParentPrice|null> */
    private static array $parentRecordCache = [];

    public static function resolveBareTitle(Item $item): string
    {
        $item->loadMissing(['group', 'tags']);
        $itemType = $item->type instanceof ItemType ? $item->type : ItemType::coerce($item->type) ?? ItemType::ITEM;
        $pcode = strtoupper(trim((string) $item->pcode));
        $builder = app(ItemIdentityBuilder::class);

        $skuTitle = self::readItemAlias($item);
        if ($skuTitle !== '') {
            return self::normalizeBareTitle($itemType, $skuTitle, $item->group, $builder);
        }

        if ($item->hasCatalogGroup()) {
            $group = $item->group;
            $storedName = (string) ($group->name ?? '');
            if ($storedName !== '' && ! self::isPcodePlaceholder($itemType, $storedName, $pcode)) {
                return self::normalizeBareTitle(
                    $itemType,
                    $builder->productDisplayName(
                        $itemType,
                        $storedName,
                        (string) ($group->variant ?? ''),
                        (string) ($group->master ?? ''),
                    ),
                    $group,
                    $builder,
                );
            }

            $groupAlias = self::readGroupAlias($group);
            if ($groupAlias !== '') {
                return self::normalizeBareTitle($itemType, $groupAlias, $group, $builder);
            }
        }

        $parentTitle = self::readParentProductName($item);
        if ($parentTitle !== '') {
            return self::normalizeBareTitle($itemType, $parentTitle, $item->group, $builder);
        }

        if ($pcode !== '') {
            return $itemType === ItemType::ITEM
                ? $builder->normalizeManufacturedPcode($pcode)
                : $pcode;
        }

        return strtoupper(trim((string) $item->name));
    }

    public static function buildDisplayName(Item $item): string
    {
        $item->loadMissing(['group', 'tags']);
        $itemType = $item->type instanceof ItemType ? $item->type : ItemType::coerce($item->type) ?? ItemType::ITEM;
        $bare = self::resolveBareTitle($item);
        $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
        $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
        if (! $sizeTag && (int) $item->size > 0) {
            $sizeTag = Tag::find((int) $item->size);
        }

        return app(ItemIdentityBuilder::class)->buildName($bare, $warnaTag, $sizeTag);
    }

    public static function syncParentProductName(string $parentKey, string $productName): void
    {
        if (! self::hasColumn('item_parent_prices', 'product_name')) {
            return;
        }

        $productName = strtoupper(trim($productName));
        $record = ItemParentPrice::query()->firstOrNew(['parent_key' => $parentKey]);
        $record->product_name = $productName;
        $record->save();
    }

    private static function normalizeBareTitle(
        ItemType $type,
        string $title,
        ?ItemGroup $group,
        ItemIdentityBuilder $builder,
    ): string {
        $title = strtoupper(trim($title));

        if ($group === null) {
            return $title;
        }

        return $builder->productDisplayName(
            $type,
            $title,
            (string) ($group->variant ?? ''),
            (string) ($group->master ?? ''),
        );
    }

    private static function isPcodePlaceholder(ItemType $type, string $storedName, string $pcode): bool
    {
        $storedName = strtoupper(trim($storedName));
        $pcode = strtoupper(trim($pcode));

        if ($storedName === '') {
            return true;
        }

        if ($pcode === '') {
            return false;
        }

        if ($storedName === $pcode) {
            return true;
        }

        if ($type === ItemType::ITEM) {
            $normalized = app(ItemIdentityBuilder::class)->normalizeManufacturedPcode($pcode);

            return $storedName === $normalized;
        }

        return false;
    }

    private static function readItemAlias(Item $item): string
    {
        if (! self::hasColumn($item->getTable(), 'alias')) {
            return '';
        }

        return strtoupper(trim((string) ($item->alias ?? '')));
    }

    private static function readGroupAlias(?ItemGroup $group): string
    {
        if ($group === null || ! self::hasColumn($group->getTable(), 'alias')) {
            return '';
        }

        return strtoupper(trim((string) ($group->getAttributes()['alias'] ?? '')));
    }

    private static function readParentProductName(Item $item): string
    {
        if (! self::hasColumn('item_parent_prices', 'product_name')) {
            return '';
        }

        $parent = self::parentRecord($item);

        return strtoupper(trim((string) ($parent?->product_name ?? '')));
    }

    private static function parentRecord(Item $item): ?ItemParentPrice
    {
        $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item);

        if (! array_key_exists($parentKey, self::$parentRecordCache)) {
            self::$parentRecordCache[$parentKey] = ItemParentPrice::query()
                ->where('parent_key', $parentKey)
                ->first();
        }

        return self::$parentRecordCache[$parentKey];
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columnExists[$key] ??= Schema::hasColumn($table, $column);
    }
}
