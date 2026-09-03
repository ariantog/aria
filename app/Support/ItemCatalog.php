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
        if ($item->hasCatalogGroup()) {
            return trim((string) ($item->group->description ?? ''));
        }

        return self::leftoverDescription($item);
    }

    public static function description2(Item $item): string
    {
        if ($item->hasCatalogGroup()) {
            return trim((string) ($item->group->description2 ?? ''));
        }

        return self::leftoverDescription2($item);
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
