<?php

namespace App\Support;

use App\Enums\ItemBrand;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Models\ItemParentPrice;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Shared catalog (description, NB, url, brand, genre, pricing defaults) lives on
 * item_parents. Colorway ({@see ItemGroup}) keeps identity + image only.
 *
 * Leftover items.* and item_group.* columns stay on production for L10 parity;
 * L12 writes catalog to the parent row via {@see applySharedCatalog()}. Reads
 * resolve parent (item_parents), then per-SKU overrides — not item_group catalog columns.
 *
 * Before a future DROP of the leftover item columns:
 * 1. Backfill item_group.brand / genre / description / description2
 * 2. Set MIRROR_ITEM_COLUMNS to false
 * 3. Deploy, then ship a guarded DROP COLUMN migration
 *
 * Do not drop items.pcode (SKU identity) or item_group.variant (group key).
 * items.variant is unused leftover and is never written here.
 */
final class ItemCatalog
{
    /**
     * Keep leftover items.description / description2 / brand / genre in sync.
     * Flip to false after backfill, before dropping those columns.
     */
    public const MIRROR_ITEM_COLUMNS = true;

    /** @var list<string> leftover items.* columns that duplicate item_group */
    public const LEFTOVER_ITEM_COLUMNS = [
        'description',
        'description2',
        'reseller_price',
        'brand',
        'genre',
        'variant',
    ];

    /** @var array<string, bool> */
    private static array $columnExists = [];

    /** @var array<string, ItemParentPrice|null> */
    private static array $parentRecordCache = [];

    public static function flushRequestCache(): void
    {
        self::$parentRecordCache = [];
        self::$columnExists = [];
    }

    public static function shouldMirrorItemColumns(): bool
    {
        return self::MIRROR_ITEM_COLUMNS;
    }

    public static function itemColumnExists(string $column): bool
    {
        return self::hasColumn((new Item)->getTable(), $column);
    }

    public static function groupColumnExists(string $column): bool
    {
        return self::hasColumn((new ItemGroup)->getTable(), $column);
    }

    public static function description(Item $item): string
    {
        return self::resolveCatalogText($item, 'description');
    }

    public static function description2(Item $item): string
    {
        return self::resolveCatalogText($item, 'description2');
    }

    /**
     * Shared colorway text lives on item_group. Leftover items.* values are per-SKU
     * overrides only when they differ from the group; mirrored duplicates read as group.
     */
    private static function resolveCatalogText(Item $item, string $field): string
    {
        $item->loadMissing('group.parent');

        $parentText = trim((string) ($item->group?->parent?->{$field} ?? ''));

        $itemText = $field === 'description'
            ? self::leftoverDescription($item)
            : self::leftoverDescription2($item);

        if ($itemText !== '' && ($parentText === '' || strtoupper($itemText) !== strtoupper($parentText))) {
            return $itemText;
        }

        if ($parentText !== '') {
            return $parentText;
        }

        return $itemText;
    }

    public static function parentDescriptionForKey(string $parentKey): string
    {
        return self::readParentFieldForKey($parentKey, 'description');
    }

    public static function parentDescription2ForKey(string $parentKey): string
    {
        return self::readParentFieldForKey($parentKey, 'description2');
    }

    public static function syncParentDescriptions(string $parentKey, string $description, string $description2): void
    {
        if (! self::hasColumn('item_parents', 'description') && ! self::hasColumn('item_parent_prices', 'description')) {
            return;
        }

        $parentKey = trim($parentKey);
        if ($parentKey === '') {
            return;
        }

        $record = \App\Models\ItemParent::query()->firstOrNew(['parent_key' => $parentKey]);
        $record->description = strtoupper(trim($description));
        $record->description2 = strtoupper(trim($description2));
        $record->save();

        self::$parentRecordCache[$parentKey] = $record->fresh();
    }

    public static function syncParentDescriptionsById(int $parentId, string $description, string $description2): void
    {
        if ($parentId <= 0) {
            return;
        }

        $record = \App\Models\ItemParent::query()->findOrFail($parentId);
        $record->description = strtoupper(trim($description));
        $record->description2 = strtoupper(trim($description2));
        $record->save();
    }

    public static function resellerPrice(Item $item): float
    {
        return ItemPricing::resolve($item, 'reseller_price');
    }

    /**
     * Unit price for sell transactions to resellers: SKU reseller price, then
     * group reseller price, then normal selling price.
     */
    public static function sellPriceForReseller(Item $item): float
    {
        $reseller = self::resellerPrice($item);

        return $reseller > 0
            ? $reseller
            : ItemPricing::resolve($item, 'price');
    }

    /**
     * Leftover items.description, even when the SKU is grouped.
     * Used to seed an empty group or scan warna when catalog text is blank.
     */
    public static function leftoverDescription(Item $item): string
    {
        return self::itemColumnExists('description')
            ? trim((string) ($item->description ?? ''))
            : '';
    }

    public static function leftoverDescription2(Item $item): string
    {
        return self::itemColumnExists('description2')
            ? trim((string) ($item->description2 ?? ''))
            : '';
    }

    public static function leftoverResellerPrice(Item $item): float
    {
        return self::itemColumnExists('reseller_price')
            ? (float) ($item->reseller_price ?? 0)
            : 0.0;
    }

    /**
     * Clear item description mirrors that only duplicate the group catalog so reads
     * fall back to item_group unless this SKU has a real local override.
     */
    public static function dedupeItemDescriptionsFromGroup(Item $item, ItemGroup $group): void
    {
        if (self::itemColumnExists('description')) {
            $itemDescription = trim((string) ($item->description ?? ''));
            $sharedDescription = self::sharedDescriptionText($group, 'description');

            if ($itemDescription !== '' && $sharedDescription !== ''
                && strtoupper($itemDescription) === $sharedDescription) {
                $item->description = '';
            }
        }

        if (self::itemColumnExists('description2')) {
            $itemDescription = trim((string) ($item->description2 ?? ''));
            $sharedDescription = self::sharedDescriptionText($group, 'description2');

            if ($itemDescription !== '' && $sharedDescription !== ''
                && strtoupper($itemDescription) === $sharedDescription) {
                $item->description2 = '';
            }
        }
    }

    /**
     * Colorway text for legacy parse / seed: group catalog first, leftover item only
     * when the group has no description yet.
     */
    public static function scanText(Item $item): string
    {
        $item->loadMissing('group');

        $catalog = trim(self::description($item).' '.self::description2($item));

        if ($catalog !== '') {
            return $catalog;
        }

        return trim(self::leftoverDescription($item).' '.self::leftoverDescription2($item));
    }

    public static function url(Item $item): string
    {
        $item->loadMissing('group.parent');

        $parentUrl = trim((string) ($item->group?->parent?->url ?? ''));
        return $parentUrl;
    }

    public static function brand(Item $item): ItemBrand
    {
        $item->loadMissing('group.parent');

        if ($item->group?->parent !== null && self::parentColumnExists('brand')) {
            $parentBrand = self::normalizeBrand($item->group->parent->brand);
            if ($parentBrand !== ItemBrand::NO_BRAND) {
                return $parentBrand;
            }
        }

        if (! self::itemColumnExists('brand')) {
            return ItemBrand::NO_BRAND;
        }

        return self::normalizeBrand($item->brand);
    }

    public static function genre(Item $item): int
    {
        $item->loadMissing('group.parent');

        if ($item->group?->parent !== null && self::parentColumnExists('genre') && (int) ($item->group->parent->genre ?? 0) > 0) {
            return (int) $item->group->parent->genre;
        }

        if (! self::itemColumnExists('genre')) {
            return 0;
        }

        return (int) ($item->genre ?? 0);
    }

    /**
     * L12 write path: persist shared catalog on item_parents (links group.parent_id).
     *
     * @param  array{
     *     description?: mixed,
     *     description2?: mixed,
     *     reseller_price?: mixed,
     *     price?: mixed,
     *     cost?: mixed,
     *     cost_cnh?: mixed,
     *     url?: mixed,
     *     product_name?: mixed,
     *     brand?: ItemBrand|int|null,
     *     genre?: int|null
     * }  $attributes
     */
    public static function applySharedCatalog(ItemGroup $group, Item $anchor, array $attributes): void
    {
        $anchor->loadMissing(['group', 'tags']);
        $anchor->setRelation('group', $group);

        $parent = ItemPricing::ensureParentForItem($anchor);
        $parentId = (int) $parent->id;

        if ((int) $group->parent_id !== $parentId) {
            $group->parent_id = $parentId;
        }

        if ($group->isDirty(['parent_id'])) {
            $group->save();
        }

        $record = \App\Models\ItemParent::query()->findOrFail($parentId);
        self::applyToParentRecord($record, $attributes);
    }

    /**
     * Backfill / admin: write catalog fields on a known item_parents row (no group re-resolution).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function applyToParentRecord(ItemParent $parent, array $attributes): bool
    {
        if ($attributes === []) {
            return false;
        }

        $parent->refresh();
        $dirty = false;

        if (array_key_exists('description', $attributes)) {
            $parent->description = self::formatParentDescription('description', $attributes['description']);
            $dirty = true;
        }

        if (array_key_exists('description2', $attributes)) {
            $parent->description2 = self::formatParentDescription('description2', $attributes['description2']);
            $dirty = true;
        }

        if (array_key_exists('url', $attributes)) {
            $parent->url = trim((string) ($attributes['url'] ?? ''));
            $dirty = true;
        }

        if (array_key_exists('product_name', $attributes) && self::parentColumnExists('product_name')) {
            $parent->product_name = self::clipParentColumnText(
                'item_parents',
                'product_name',
                strtoupper(trim((string) ($attributes['product_name'] ?? ''))),
            );
            $dirty = true;
        }

        if (array_key_exists('brand', $attributes) && self::parentColumnExists('brand')) {
            $parent->brand = self::normalizeBrand($attributes['brand']);
            $dirty = true;
        }

        if (array_key_exists('genre', $attributes) && self::parentColumnExists('genre')) {
            $parent->genre = (int) ($attributes['genre'] ?? 0);
            $dirty = true;
        }

        foreach (['price', 'reseller_price', 'cost', 'cost_cnh'] as $priceField) {
            if (array_key_exists($priceField, $attributes)) {
                $parent->{$priceField} = max(0, (float) ($attributes[$priceField] ?? 0));
                $dirty = true;
            }
        }

        if (! $dirty) {
            return false;
        }

        $parent->save();
        self::flushParentCatalogCaches();

        return true;
    }

    private static function flushParentCatalogCaches(): void
    {
        ItemPricing::flushRequestCache();
        self::$parentRecordCache = [];
    }

    private static function formatParentDescription(string $column, mixed $value): string
    {
        $text = strtoupper(trim((string) $value));

        return self::clipParentColumnText('item_parents', $column, $text);
    }

    private static function clipParentColumnText(string $table, string $column, string $text): string
    {
        $max = self::stringColumnMaxLength($table, $column);
        if ($max === null || strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max);
    }

    private static function stringColumnMaxLength(string $table, string $column): ?int
    {
        static $cache = [];

        $cacheKey = $table.'.'.$column;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (! self::hasColumn($table, $column)) {
            $cache[$cacheKey] = null;

            return null;
        }

        try {
            $type = Schema::getColumnType($table, $column);
        } catch (\Throwable) {
            $type = 'string';
        }

        $cache[$cacheKey] = match ($type) {
            'text', 'mediumText', 'longText' => null,
            default => match ($column) {
                'description' => 200,
                'description2' => 250,
                'product_name' => 255,
                'url' => 500,
                default => 255,
            },
        };

        return $cache[$cacheKey];
    }

    public static function parentDescriptionsUseTextColumn(): bool
    {
        if (! self::hasColumn('item_parents', 'description')) {
            return false;
        }

        try {
            return Schema::getColumnType('item_parents', 'description') === 'text';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @deprecated Use {@see applySharedCatalog()} for L12 writes. Group-only path remains for legacy tests.
     * Persist catalog fields on the group. Always the write path for shared attributes.
     *
     * @param  array{
     *     description?: mixed,
     *     description2?: mixed,
     *     reseller_price?: mixed,
     *     url?: mixed,
     *     brand?: ItemBrand|int|null,
     *     genre?: int|null
     * }  $attributes
     */
    public static function applyToGroup(ItemGroup $group, array $attributes, ?Item $catalogAnchor = null): void
    {
        if ($catalogAnchor !== null) {
            self::applySharedCatalog($group, $catalogAnchor, $attributes);

            return;
        }

        if (array_key_exists('description', $attributes)) {
            $group->description = strtoupper((string) ($attributes['description'] ?? ''));
        }

        if (array_key_exists('description2', $attributes)) {
            $group->description2 = strtoupper((string) ($attributes['description2'] ?? ''));
        }

        if (array_key_exists('reseller_price', $attributes) && self::groupColumnExists('reseller_price')) {
            $group->reseller_price = max(0, (float) ($attributes['reseller_price'] ?? 0));
        }

        foreach (['price', 'cost', 'cost_cnh'] as $priceField) {
            if (array_key_exists($priceField, $attributes) && self::groupColumnExists($priceField)) {
                $group->{$priceField} = max(0, (float) ($attributes[$priceField] ?? 0));
            }
        }

        if (array_key_exists('url', $attributes)) {
            $group->url = $attributes['url'];
        }

        if (array_key_exists('brand', $attributes) && self::groupColumnExists('brand')) {
            $group->brand = self::normalizeBrand($attributes['brand']);
        }

        if (array_key_exists('genre', $attributes) && self::groupColumnExists('genre')) {
            $group->genre = (int) ($attributes['genre'] ?? 0);
        }

        $group->save();
    }

    public static function parentColumnExists(string $column): bool
    {
        return self::hasColumn('item_parents', $column)
            || self::hasColumn('item_parent_prices', $column);
    }

    private static function sharedDescriptionText(ItemGroup $group, string $field): string
    {
        $group->loadMissing('parent');

        if ($group->parent !== null && self::parentColumnExists($field)) {
            $fromParent = trim((string) ($group->parent->{$field} ?? ''));
            if ($fromParent !== '') {
                return strtoupper($fromParent);
            }
        }

        return '';
    }

    /**
     * Shared description lives on item_group. Clear stale items.* copies whenever
     * the colorway field is saved so detail pages read the group value. Per-SKU text
     * uses item_description / item_description2 on the item being edited.
     *
     * @param  array{description?: string, description2?: string}  $previousGroupText
     */
    public static function syncDescriptionMirrorsForGroup(
        ItemGroup $group,
        object $input,
        Item $editedItem,
        array $previousGroupText = [],
    ): void {
        $group->refresh();

        foreach ([
            'description' => 'item_description',
            'description2' => 'item_description2',
        ] as $field => $overrideKey) {
            /** @var string $overrideKey */
            if (! isset($input->{$field}) && ! property_exists($input, $field)) {
                continue;
            }

            if (! self::itemColumnExists($field)) {
                continue;
            }

            $override = '';
            if (property_exists($input, $overrideKey) || isset($input->{$overrideKey})) {
                $override = strtoupper(trim((string) ($input->{$overrideKey} ?? '')));
            }

            $newSharedText = self::sharedDescriptionText($group, $field);
            $previousGroupTextValue = strtoupper(trim((string) ($previousGroupText[$field] ?? '')));

            Item::query()
                ->where('group_id', $group->id)
                ->get()
                ->each(function (Item $item) use (
                    $field,
                    $override,
                    $overrideKey,
                    $input,
                    $editedItem,
                    $newSharedText,
                    $previousGroupTextValue,
                ): void {
                    if ($item->id === $editedItem->id) {
                        if (property_exists($input, $overrideKey) || isset($input->{$overrideKey})) {
                            $item->{$field} = $override;
                            $item->save();
                        }

                        return;
                    }

                    $itemText = strtoupper(trim((string) ($item->{$field} ?? '')));
                    if ($itemText === '') {
                        return;
                    }

                    $isMirror = $previousGroupTextValue !== ''
                        && $itemText === $previousGroupTextValue;
                    $matchesNewShared = $newSharedText !== '' && $itemText === $newSharedText;

                    if ($isMirror || $matchesNewShared) {
                        $item->{$field} = '';
                        $item->save();
                    }
                });
        }
    }

    /**
     * Colorway editor updates the parent catalog — drop leftover item mirrors for every size.
     */
    public static function resetDescriptionMirrorsForColorway(ItemGroup $group): void
    {
        $updates = [];

        if (self::itemColumnExists('description')) {
            $updates['description'] = '';
        }

        if (self::itemColumnExists('description2')) {
            $updates['description2'] = '';
        }

        if ($updates === []) {
            return;
        }

        Item::query()->where('group_id', $group->id)->update($updates);
    }

    /**
     * After a colorway catalog save, clear only mirrored items.* copies for the fields
     * that were submitted (description and/or NB / description2). SKUs with empty text
     * already inherit the group (protected). Real local overrides are kept.
     *
     * @param  array{description?: string, description2?: string}  $previousGroupText
     * @param  list<'description'|'description2'>  $fields
     */
    public static function clearStaleDescriptionMirrorsForColorway(
        ItemGroup $group,
        array $previousGroupText,
        array $fields = ['description', 'description2'],
    ): void {
        $group->refresh();

        foreach ($fields as $field) {
            if (! in_array($field, ['description', 'description2'], true)) {
                continue;
            }
            if (! self::itemColumnExists($field)) {
                continue;
            }

            $newSharedText = self::sharedDescriptionText($group, $field);
            $previousGroupTextValue = strtoupper(trim((string) ($previousGroupText[$field] ?? '')));

            Item::query()
                ->where('group_id', $group->id)
                ->get()
                ->each(function (Item $item) use (
                    $field,
                    $newSharedText,
                    $previousGroupTextValue,
                ): void {
                    $itemText = strtoupper(trim((string) ($item->{$field} ?? '')));

                    if ($itemText === '') {
                        return;
                    }

                    $isMirror = $previousGroupTextValue !== ''
                        && $itemText === $previousGroupTextValue;
                    $matchesNewShared = $newSharedText !== '' && $itemText === $newSharedText;

                    if ($isMirror || $matchesNewShared) {
                        $item->{$field} = '';
                        $item->save();
                    }
                });
        }
    }

    /**
     * Fill empty group description fields from a previous leftover group or the
     * item leftover columns. Never overwrites a non-empty group value.
     */
    public static function seedEmptyDescriptions(ItemGroup $group, Item $item, ?ItemGroup $sourceGroup = null): void
    {
        $attributes = [];

        if (self::sharedDescriptionText($group, 'description') === '') {
            $seed = self::firstNonEmpty(
                trim((string) ($sourceGroup?->description ?? '')),
                self::leftoverDescription($item),
            );

            if ($seed !== '') {
                $attributes['description'] = $seed;
            }
        }

        if (self::sharedDescriptionText($group, 'description2') === '') {
            $seed = self::firstNonEmpty(
                trim((string) ($sourceGroup?->description2 ?? '')),
                self::leftoverDescription2($item),
            );

            if ($seed !== '') {
                $attributes['description2'] = $seed;
            }
        }

        if ($attributes !== []) {
            self::applySharedCatalog($group, $item, $attributes);
        }
    }

    private static function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    /**
     * Copy catalog fields onto leftover item columns. No-op when mirroring is off
     * or the column has already been dropped. Does not save the item.
     *
     * @param  array{
     *     description?: mixed,
     *     description2?: mixed,
     *     reseller_price?: mixed,
     *     brand?: ItemBrand|int|null,
     *     genre?: int|null
     * }  $attributes
     */
    public static function mirrorToItem(Item $item, array $attributes): void
    {
        if (! self::shouldMirrorItemColumns()) {
            return;
        }

        if (array_key_exists('description', $attributes) && self::itemColumnExists('description')) {
            $item->description = $attributes['description'] ?? '';
        }

        if (array_key_exists('description2', $attributes) && self::itemColumnExists('description2')) {
            $item->description2 = $attributes['description2'] ?? '';
        }

        if (array_key_exists('reseller_price', $attributes) && self::itemColumnExists('reseller_price')) {
            $item->reseller_price = max(0, (float) ($attributes['reseller_price'] ?? 0));
        }

        if (array_key_exists('brand', $attributes) && self::itemColumnExists('brand')) {
            $item->brand = self::normalizeBrand($attributes['brand']);
        }

        if (array_key_exists('genre', $attributes) && self::itemColumnExists('genre')) {
            $item->genre = (int) ($attributes['genre'] ?? 0);
        }
    }

    public static function constrainBrand(Builder $query, int $brand): void
    {
        $query->where(function (Builder $q) use ($brand) {
            if (self::groupColumnExists('brand')) {
                $q->whereExists(function ($sub) use ($brand) {
                    $sub->selectRaw('1')
                        ->from('item_group')
                        ->whereColumn('item_group.id', 'items.group_id')
                        ->where('items.group_id', '>', 0)
                        ->where('item_group.brand', $brand);
                });
            }

            if (! self::shouldMirrorItemColumns() || ! self::itemColumnExists('brand')) {
                return;
            }

            $q->orWhere(function (Builder $item) use ($brand) {
                $item->where('items.brand', $brand)
                    ->where(function (Builder $fallback) {
                        $fallback->whereNull('items.group_id')
                            ->orWhere('items.group_id', '<=', 0);

                        if (self::groupColumnExists('brand')) {
                            $fallback->orWhereNotExists(function ($sub) {
                                $sub->selectRaw('1')
                                    ->from('item_group')
                                    ->whereColumn('item_group.id', 'items.group_id')
                                    ->where('items.group_id', '>', 0)
                                    ->where('item_group.brand', '>', 0);
                            });
                        }
                    });
            });
        });
    }

    public static function clearGroupGenre(int $tagId): void
    {
        if (! self::groupColumnExists('genre') || $tagId <= 0) {
            return;
        }

        ItemGroup::query()->where('genre', $tagId)->update(['genre' => 0]);
    }

    public static function normalizeBrand(mixed $brand): ItemBrand
    {
        if ($brand instanceof ItemBrand) {
            return $brand;
        }

        return ItemBrand::tryFrom((int) $brand) ?? ItemBrand::NO_BRAND;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columnExists[$key] ??= Schema::hasColumn($table, $column);
    }

    private static function readParentCatalogText(Item $item, string $field): string
    {
        if (! in_array($field, ['description', 'description2'], true)
            || ! self::hasColumn('item_parent_prices', $field)) {
            return '';
        }

        $parent = self::parentRecord($item);

        return strtoupper(trim((string) ($parent?->{$field} ?? '')));
    }

    private static function readParentFieldForKey(string $parentKey, string $field): string
    {
        if (! self::hasColumn('item_parents', $field)) {
            return '';
        }

        $parentKey = trim($parentKey);
        if ($parentKey === '') {
            return '';
        }

        if (! array_key_exists($parentKey, self::$parentRecordCache)) {
            self::$parentRecordCache[$parentKey] = ItemParent::query()
                ->where('parent_key', $parentKey)
                ->orderBy('id')
                ->first();
        }

        return strtoupper(trim((string) (self::$parentRecordCache[$parentKey]?->{$field} ?? '')));
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
}
