<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\User;
use App\Services\WarehouseCompare\WarehouseCompareService;
use App\Support\UserPreferenceRegistry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WarehouseComparePreferenceService
{
    public function __construct(
        protected UserPreferenceService $preferences,
        protected LocationAccessService $locationAccess,
    ) {}

    /**
     * @return array{warehouse_ids: list<int>, item_type: string, sort: string}
     */
    public function defaults(User $user): array
    {
        $stored = $this->preferences->get($user, UserPreferenceRegistry::WAREHOUSE_COMPARE_SLUG, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return [
            'warehouse_ids' => $this->normalizeWarehouseIds($stored['warehouse_ids'] ?? [], $user),
            'item_type' => $this->normalizeItemType($stored['item_type'] ?? ItemType::ASSET_LANCAR->value),
            'sort' => $this->normalizeSort($stored['sort'] ?? WarehouseCompareService::SORT_SKU),
        ];
    }

    /**
     * @param  array{warehouse_ids?: mixed, item_type?: mixed, sort?: mixed}  $input
     */
    public function save(User $user, array $input): void
    {
        $warehouseIds = $this->normalizeWarehouseIds($input['warehouse_ids'] ?? [], $user, strict: true);
        $itemType = $this->normalizeItemType($input['item_type'] ?? ItemType::ASSET_LANCAR->value);
        $sort = $this->normalizeSort($input['sort'] ?? WarehouseCompareService::SORT_SKU);

        $this->preferences->set($user, UserPreferenceRegistry::WAREHOUSE_COMPARE_SLUG, [
            'warehouse_ids' => $warehouseIds,
            'item_type' => $itemType,
            'sort' => $sort,
        ]);
    }

    /**
     * @return Collection<int, Addrbook>
     */
    public function selectableWarehouses(User $user): Collection
    {
        return Addrbook::query()
            ->where('type', \App\Enums\AddrbookType::Warehouse)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Addrbook $warehouse) => $this->locationAccess->canAccessAddrbook($user, $warehouse))
            ->values();
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    public function normalizeWarehouseIds(mixed $raw, User $user, bool $strict = false): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) > UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES) {
            if ($strict) {
                throw new InvalidArgumentException('You can compare at most '.UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES.' warehouses.');
            }

            $ids = array_slice($ids, 0, UserPreferenceRegistry::WAREHOUSE_COMPARE_MAX_WAREHOUSES);
        }

        $allowed = $this->selectableWarehouses($user)->pluck('id')->all();
        $filtered = array_values(array_filter($ids, fn (int $id) => in_array($id, $allowed, true)));

        if ($strict && count($filtered) !== count($ids)) {
            throw new InvalidArgumentException('One or more warehouses are invalid or not available for your account.');
        }

        return $filtered;
    }

    public function normalizeItemType(mixed $raw): string
    {
        $type = ItemType::coerce($raw);

        if ($type === ItemType::ITEM) {
            return (string) ItemType::ITEM->value;
        }

        return (string) ItemType::ASSET_LANCAR->value;
    }

    public function normalizeSort(mixed $raw): string
    {
        $sort = is_string($raw) ? strtolower(trim($raw)) : '';

        return in_array($sort, WarehouseCompareService::validSorts(), true)
            ? $sort
            : WarehouseCompareService::SORT_SKU;
    }
}
