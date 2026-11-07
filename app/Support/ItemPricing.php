<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Item prices resolve: this SKU → parent (item_parents via item_group.parent_id).
 *
 * A stored value of 0 on the SKU means inherit from parent. Colorway (item_group) does not store prices.
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

    /** @var array<int, ItemParent|null> */
    private static array $parentRecordByIdCache = [];

    public static function flushRequestCache(): void
    {
        self::$parentRecordByIdCache = [];
        self::$columnExists = [];
    }

    public static function parentGroupHasStoredPricing(int $parentId): bool
    {
        if ($parentId <= 0) {
            return false;
        }

        $record = self::parentRecordById($parentId);
        if ($record === null) {
            return false;
        }

        foreach (self::FIELDS as $field) {
            if (self::isSet(self::readParentColumn($record, $field))) {
                return true;
            }
        }

        return false;
    }

    public static function resolve(Item $item, string $field): float
    {
        self::assertField($field);
        $item->loadMissing('group.parent');

        $sizeValue = self::readItemColumn($item, $field);
        if (self::isSet($sizeValue)) {
            return $sizeValue;
        }

        $parentValue = self::readParentColumn($item->group?->parent, $field);
        if (self::isSet($parentValue)) {
            return $parentValue;
        }

        return self::readGroupColumn($item->group, $field);
    }

    public static function detectScope(Item $item, string $field): string
    {
        self::assertField($field);
        $item->loadMissing('group.parent');

        if (self::isSet(self::readItemColumn($item, $field))) {
            return self::SCOPE_SIZE;
        }

        $parent = $item->group?->parent;
        if ($parent !== null && self::isSet(self::readParentColumn($parent, $field))) {
            return self::SCOPE_GROUP;
        }

        return self::SCOPE_SIZE;
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
     * @return array<string, array{scope: string, value: float, effective: float}>
     */
    public static function formStateForSkuEdit(Item $item): array
    {
        $state = [];

        foreach (self::FIELDS as $field) {
            $stored = self::readItemColumn($item, $field);
            $effective = self::resolve($item, $field);

            $state[$field] = [
                'scope' => self::SCOPE_SIZE,
                'value' => self::isSet($stored) ? $stored : $effective,
                'effective' => $effective,
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

            $scope = (string) ($row['scope'] ?? self::SCOPE_SIZE);
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
            self::SCOPE_COLORWAY => self::writeColorwaySizes($item, $field, $value),
            self::SCOPE_GROUP => self::writeParentFromItem($item, $field, $value),
            default => self::writeSize($item, $field, $value),
        };
    }

    /**
     * @return array<string, array{scope: string, value: float, effective: float}>
     */
    public static function formStateForParent(int $parentId, ?Item $sample = null): array
    {
        $record = self::parentRecordById($parentId);
        $state = [];

        foreach (self::FIELDS as $field) {
            $stored = $record !== null ? self::readParentColumn($record, $field) : 0.0;
            $scope = self::isSet($stored) ? self::SCOPE_GROUP : self::SCOPE_SIZE;
            $effective = $sample !== null ? self::resolve($sample, $field) : $stored;

            $state[$field] = [
                'scope' => $scope,
                'value' => self::isSet($stored) ? $stored : 0.0,
                'effective' => $effective,
            ];
        }

        return $state;
    }

    /**
     * @param  array<string, array{scope?: mixed, value?: mixed}>  $rows
     */
    public static function applyForParent(int $parentId, array $rows, bool $cascadeOverrides = false): void
    {
        if ($parentId <= 0) {
            return;
        }

        $groupIds = ItemGroup::query()
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

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

            $record = ItemParent::query()->findOrFail($parentId);
            self::writeParentColumn($record, $field, $value);
            $record->save();
            self::$parentRecordByIdCache[$parentId] = $record->fresh();

            if ($value > 0 && $cascadeOverrides && $groupIds !== []) {
                Item::query()->whereIn('group_id', $groupIds)->update([$field => 0]);
            }
        }
    }

    private static function writeSize(Item $item, string $field, float $value): void
    {
        self::writeItemColumn($item, $field, $value);
        $item->save();
    }

    /**
     * Colorway editor: apply one amount to every SKU in the colorway (not item_group).
     */
    private static function writeColorwaySizes(Item $item, string $field, float $value): void
    {
        $item->loadMissing('group');
        $group = $item->group;

        if ($group === null) {
            self::writeSize($item, $field, $value);

            return;
        }

        if ((int) ($group->parent_id ?? 0) > 0) {
            self::writeParentFromItem($item, $field, $value);

            return;
        }

        Item::query()
            ->where('group_id', $group->id)
            ->update([$field => $value]);
    }

    private static function writeParentFromItem(Item $item, string $field, float $value): void
    {
        $item->loadMissing('group');
        $parentId = (int) ($item->group?->parent_id ?? 0);

        if ($parentId <= 0) {
            self::writeSize($item, $field, $value);

            return;
        }

        $record = ItemParent::query()->findOrFail($parentId);
        self::writeParentColumn($record, $field, $value);
        $record->save();
        self::$parentRecordByIdCache[$parentId] = $record->fresh();

        $groupIds = ItemGroup::query()->where('parent_id', $parentId)->pluck('id')->all();

        if ($groupIds !== []) {
            Item::query()->whereIn('group_id', $groupIds)->update([$field => 0]);
        }
    }

    private static function storedValueForScope(Item $item, string $field, string $scope): float
    {
        return match ($scope) {
            self::SCOPE_SIZE => self::readItemColumn($item, $field),
            self::SCOPE_GROUP => self::readParentColumn($item->group?->parent, $field),
            default => self::readItemColumn($item, $field),
        };
    }

    private static function parentRecordById(int $parentId): ?ItemParent
    {
        if ($parentId <= 0) {
            return null;
        }

        if (! array_key_exists($parentId, self::$parentRecordByIdCache)) {
            self::$parentRecordByIdCache[$parentId] = ItemParent::query()->find($parentId);
        }

        return self::$parentRecordByIdCache[$parentId];
    }

    public static function parentIdForItem(Item $item): int
    {
        $item->loadMissing('group');

        return (int) ($item->group?->parent_id ?? 0);
    }

    public static function ensureParentForItem(Item $item): ItemParent
    {
        $item->loadMissing(['group', 'tags']);
        $parentId = self::parentIdForItem($item);

        if ($parentId > 0) {
            return ItemParent::query()->findOrFail($parentId);
        }

        $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item);
        $record = null;

        if (Schema::hasColumn('item_parents', 'parent_key')) {
            $record = ItemParent::query()->where('parent_key', $parentKey)->orderBy('id')->first();
        }

        if ($record === null) {
            $record = new ItemParent;
            if (Schema::hasColumn('item_parents', 'parent_key')) {
                $record->parent_key = $parentKey;
            }
            $record->save();
        }

        if ($item->group !== null && (int) $item->group->parent_id !== (int) $record->id) {
            $item->group->parent_id = (int) $record->id;
            $item->group->save();
        }

        self::$parentRecordByIdCache[(int) $record->id] = $record->fresh();

        return $record;
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

    private static function readParentColumn(?ItemParent $record, string $field): float
    {
        if ($record === null) {
            return 0.0;
        }

        return (float) ($record->{$field} ?? 0);
    }

    private static function readGroupColumn(?ItemGroup $group, string $field): float
    {
        if ($group === null || ! self::hasColumn($group->getTable(), $field)) {
            return 0.0;
        }

        return (float) ($group->{$field} ?? 0);
    }

    private static function writeParentColumn(ItemParent $record, string $field, float $value): void
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
