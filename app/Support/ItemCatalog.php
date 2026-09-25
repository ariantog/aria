<?php

namespace App\Support;

use App\Enums\ItemBrand;
use App\Models\Item;
use App\Models\ItemGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Shared colorway attributes live on item_group.
 *
 * Leftover items.* columns stay on the production table for L10 / stats
 * compatibility. Application reads must go through this class (or Item
 * catalog* helpers). Writes still mirror onto the item while
 * {@see self::MIRROR_ITEM_COLUMNS} is true.
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
        $item->loadMissing('group');

        $groupText = $item->hasCatalogGroup()
            ? trim((string) ($item->group->{$field} ?? ''))
            : '';

        $itemText = $field === 'description'
            ? self::leftoverDescription($item)
            : self::leftoverDescription2($item);

        if ($groupText !== '') {
            if ($itemText !== '' && strtoupper($itemText) !== strtoupper($groupText)) {
                return $itemText;
            }

            return $groupText;
        }

        return $itemText;
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
            $groupDescription = trim((string) ($group->description ?? ''));

            if ($itemDescription !== '' && strtoupper($itemDescription) === strtoupper($groupDescription)) {
                $item->description = '';
            }
        }

        if (self::itemColumnExists('description2')) {
            $itemDescription = trim((string) ($item->description2 ?? ''));
            $groupDescription = trim((string) ($group->description2 ?? ''));

            if ($itemDescription !== '' && strtoupper($itemDescription) === strtoupper($groupDescription)) {
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

    public static function brand(Item $item): ItemBrand
    {
        if ($item->hasCatalogGroup() && self::groupColumnExists('brand')) {
            $groupBrand = self::normalizeBrand($item->group->brand);

            if ($groupBrand !== ItemBrand::NO_BRAND) {
                return $groupBrand;
            }
        }

        if (! self::itemColumnExists('brand')) {
            return ItemBrand::NO_BRAND;
        }

        return self::normalizeBrand($item->brand);
    }

    public static function genre(Item $item): int
    {
        if ($item->hasCatalogGroup() && self::groupColumnExists('genre') && (int) ($item->group->genre ?? 0) > 0) {
            return (int) $item->group->genre;
        }

        if (! self::itemColumnExists('genre')) {
            return 0;
        }

        return (int) ($item->genre ?? 0);
    }

    /**
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
    public static function applyToGroup(ItemGroup $group, array $attributes): void
    {
        if (array_key_exists('description', $attributes) && $attributes['description'] !== null) {
            $group->description = strtoupper((string) $attributes['description']);
        }

        if (array_key_exists('description2', $attributes) && $attributes['description2'] !== null) {
            $group->description2 = strtoupper((string) $attributes['description2']);
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

            $newGroupText = strtoupper(trim((string) ($group->{$field} ?? '')));
            $previousGroupTextValue = strtoupper(trim((string) ($previousGroupText[$field] ?? '')));

            Item::query()
                ->where('group_id', $group->id)
                ->get()
                ->each(function (Item $item) use (
                    $field,
                    $override,
                    $editedItem,
                    $newGroupText,
                    $previousGroupTextValue,
                ): void {
                    if ($item->id === $editedItem->id) {
                        $item->{$field} = $override;
                        $item->save();

                        return;
                    }

                    $itemText = strtoupper(trim((string) ($item->{$field} ?? '')));
                    if ($itemText === '') {
                        return;
                    }

                    $isMirror = $previousGroupTextValue !== ''
                        && $itemText === $previousGroupTextValue;
                    $matchesNewGroup = $newGroupText !== '' && $itemText === $newGroupText;

                    if ($isMirror || $matchesNewGroup) {
                        $item->{$field} = '';
                        $item->save();
                    }
                });
        }
    }

    /**
     * Colorway editor updates the group only — drop leftover item mirrors for every size.
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
     * Fill empty group description fields from a previous leftover group or the
     * item leftover columns. Never overwrites a non-empty group value.
     */
    public static function seedEmptyDescriptions(ItemGroup $group, Item $item, ?ItemGroup $sourceGroup = null): void
    {
        $attributes = [];

        if (trim((string) ($group->description ?? '')) === '') {
            $seed = self::firstNonEmpty(
                trim((string) ($sourceGroup?->description ?? '')),
                self::leftoverDescription($item),
            );

            if ($seed !== '') {
                $attributes['description'] = $seed;
            }
        }

        if (trim((string) ($group->description2 ?? '')) === '') {
            $seed = self::firstNonEmpty(
                trim((string) ($sourceGroup?->description2 ?? '')),
                self::leftoverDescription2($item),
            );

            if ($seed !== '') {
                $attributes['description2'] = $seed;
            }
        }

        if ($attributes !== []) {
            self::applyToGroup($group, $attributes);
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
}
