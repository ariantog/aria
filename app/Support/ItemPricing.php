<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Item prices resolve in order: this SKU → colorway (item_group) → parent group.
 *
 * A stored value of 0 at a level means "inherit from the next level".
 */
final class ItemPricing
{
    public const SCOPE_SIZE = 'size';

    public const SCOPE_COLORWAY = 'colorway';

    public const SCOPE_GROUP = 'group';

    /** @var list<string> */
    public const FIELDS = [
        'price',
        'reseller_price',
        'cost',
        'cost_cnh',
    ];

    /** @var array<string, bool> */
    private static array $columnExists = [];

    public static function resolve(Item $item, string $field): float
    {
        self::assertField($field);
        $item->loadMissing('group');

        $sizeValue = self::readItemColumn($item, $field);
        if (self::isSet($sizeValue)) {
            return $sizeValue;
        }

        if ($item->hasCatalogGroup()) {
            $colorwayValue = self::readGroupColumn($item->group, $field);
            if (self::isSet($colorwayValue)) {
                return $colorwayValue;
            }
        }

        $parent = self::parentRecord($item);

        return $parent !== null ? self::readParentColumn($parent, $field) : 0.0;
    }

    public static function detectScope(Item $item, string $field): string
    {
        self::assertField($field);
        $item->loadMissing('group');

        if (self::isSet(self::readItemColumn($item, $field))) {
            return self::SCOPE_SIZE;
        }

        if ($item->hasCatalogGroup() && self::isSet(self::readGroupColumn($item->group, $field))) {
            return self::SCOPE_COLORWAY;
        }

        $parent = self::parentRecord($item);
        if ($parent !== null && self::isSet(self::readParentColumn($parent, $field))) {
            return self::SCOPE_GROUP;
        }

        return self::SCOPE_COLORWAY;
    }

    /**
     * @return array<string, array{scope: string, value: float, effective: float}>
     */
    public static function formState(Item $item): array
    {
        $state = [];

        foreach (self::FIELDS as $field) {
            $scope = self::detectScope($item, $field);
            $state[$field] = [
                'scope' => $scope,
                'value' => self::storedValueForScope($item, $field, $scope),
                'effective' => self::resolve($item, $field),
            ];
        }

        return $state;
    }

    /**
     * @param  array<string, array{scope?: mixed, value?: mixed}>  $rows
     */
    public static function applyFormRows(Item $item, array $rows): void
    {
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $rows)) {
                continue;
            }

            $row = $rows[$field];
            if (! is_array($row)) {
                continue;
            }

            $scope = (string) ($row['scope'] ?? self::SCOPE_COLORWAY);
            $value = max(0, (float) ($row['value'] ?? 0));

            self::apply($item, $field, $scope, $value);
        }
    }

    public static function apply(Item $item, string $field, string $scope, float $value): void
    {
        self::assertField($field);
        $value = max(0, $value);

        match ($scope) {
            self::SCOPE_SIZE => self::writeSize($item, $field, $value),
            self::SCOPE_COLORWAY => self::writeColorway($item, $field, $value),
            self::SCOPE_GROUP => self::writeGroup($item, $field, $value),
            default => self::writeColorway($item, $field, $value),
        };
    }

    /**
     * @return array<string, array{scope: string, value: float, effective: float}>
     */
    public static function formStateForParent(string $parentKey, ?Item $sample = null): array
    {
        $record = ItemParentPrice::query()->where('parent_key', $parentKey)->first();
        $state = [];

        foreach (self::FIELDS as $field) {
            $stored = $record !== null ? self::readParentColumn($record, $field) : 0.0;
            $scope = self::isSet($stored) ? self::SCOPE_GROUP : self::SCOPE_COLORWAY;
            $effective = $sample !== null ? self::resolve($sample, $field) : $stored;

            $state[$field] = [
                'scope' => $scope,
                'value' => self::isSet($stored) ? $stored : 0.0,
                'effective' => $effective,
            ];
        }

        return $state;
    }

    public static function applyForParent(string $parentKey, array $rows): void
    {
        $hierarchy = app(ItemGroupHierarchyService::class);
        $groupIds = $hierarchy->groupIdsForParentKey($parentKey);

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $rows)) {
                continue;
            }

            $row = $rows[$field];
            if (! is_array($row)) {
                continue;
            }

            $scope = (string) ($row['scope'] ?? self::SCOPE_GROUP);
            $value = max(0, (float) ($row['value'] ?? 0));

            if ($scope !== self::SCOPE_GROUP) {
                continue;
            }

            $record = ItemParentPrice::query()->firstOrNew(['parent_key' => $parentKey]);
            self::writeParentColumn($record, $field, $value);
            $record->save();

            if ($groupIds !== []) {
                ItemGroup::query()->whereIn('id', $groupIds)->update([$field => 0]);
                Item::query()->whereIn('group_id', $groupIds)->update([$field => 0]);
            }
        }
    }

    private static function writeSize(Item $item, string $field, float $value): void
    {
        self::writeItemColumn($item, $field, $value);
        $item->save();
    }

    private static function writeColorway(Item $item, string $field, float $value): void
    {
        $item->loadMissing('group');
        $group = $item->group;

        if ($group === null) {
            self::writeSize($item, $field, $value);

            return;
        }

        self::writeGroupColumn($group, $field, $value);
        $group->save();

        Item::query()
            ->where('group_id', $group->id)
            ->update([$field => 0]);
    }

    private static function writeGroup(Item $item, string $field, float $value): void
    {
        $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item);
        $record = ItemParentPrice::query()->firstOrNew(['parent_key' => $parentKey]);
        self::writeParentColumn($record, $field, $value);
        $record->save();

        $groupIds = app(ItemGroupHierarchyService::class)->groupIdsForParentKey($parentKey);

        if ($groupIds !== []) {
            ItemGroup::query()->whereIn('id', $groupIds)->update([$field => 0]);
            Item::query()->whereIn('group_id', $groupIds)->update([$field => 0]);
        }
    }

    private static function storedValueForScope(Item $item, string $field, string $scope): float
    {
        return match ($scope) {
            self::SCOPE_SIZE => self::readItemColumn($item, $field),
            self::SCOPE_COLORWAY => $item->hasCatalogGroup()
                ? self::readGroupColumn($item->group, $field)
                : 0.0,
            self::SCOPE_GROUP => self::readParentColumn(self::parentRecord($item), $field),
            default => 0.0,
        };
    }

    private static function parentRecord(Item $item): ?ItemParentPrice
    {
        $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item);

        return ItemParentPrice::query()->where('parent_key', $parentKey)->first();
    }

    private static function isSet(float $value): bool
    {
        return $value > 0;
    }

    private static function readItemColumn(Item $item, string $field): float
    {
        if (! self::hasColumn($item->getTable(), $field)) {
            return 0.0;
        }

        return (float) ($item->{$field} ?? 0);
    }

    private static function writeItemColumn(Item $item, string $field, float $value): void
    {
        if (self::hasColumn($item->getTable(), $field)) {
            $item->{$field} = $value;
        }
    }

    private static function readGroupColumn(?ItemGroup $group, string $field): float
    {
        if ($group === null || ! self::hasColumn($group->getTable(), $field)) {
            return 0.0;
        }

        return (float) ($group->{$field} ?? 0);
    }

    private static function writeGroupColumn(ItemGroup $group, string $field, float $value): void
    {
        if (self::hasColumn($group->getTable(), $field)) {
            $group->{$field} = $value;
        }
    }

    private static function readParentColumn(?ItemParentPrice $record, string $field): float
    {
        if ($record === null) {
            return 0.0;
        }

        return (float) ($record->{$field} ?? 0);
    }

    private static function writeParentColumn(ItemParentPrice $record, string $field, float $value): void
    {
        $record->{$field} = $value;
    }

    private static function assertField(string $field): void
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw new \InvalidArgumentException("Unknown pricing field: {$field}");
        }
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columnExists[$key] ??= Schema::hasColumn($table, $column);
    }
}
