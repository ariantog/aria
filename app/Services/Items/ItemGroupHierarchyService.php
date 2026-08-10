<?php

namespace App\Services\Items;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Item;
use App\Services\JubelioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemGroupHierarchyService
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
        protected JubelioService $jubelioService,
    ) {}

    /**
     * @param  array{kode?: string, product_name?: string, desc?: string}  $filters
     */
    public function paginateParents(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $parents = $this->buildParentSummaries($filters);
        $page = max(1, (int) request()->query('page', 1));
        $slice = $parents->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $slice,
            $parents->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parentDetail(string $parentKey, bool $fetchJubelio = true): ?array
    {
        $items = $this->itemsForParentKey($parentKey);
        if ($items->isEmpty()) {
            return null;
        }

        $sample = $items->first();
        $itemType = $sample->type;
        $label = $this->identityBuilder->itemParentLabel($sample);
        $productName = $this->resolveProductName($items, $parentKey, $itemType);
        $usesPlaceholder = $itemType === ItemType::ITEM
            && $productName !== ''
            && strtoupper($productName) === strtoupper($this->identityBuilder->manufacturedParentMaster($sample));

        $jubelioStocks = $fetchJubelio
            ? $this->jubelioService->fetchItemStocks(
                $items->pluck('jubelio_item_id')->filter(fn ($id) => (int) $id > 0)->unique()->values()->all()
            )
            : [];

        $colors = $this->buildColorSections($items, $jubelioStocks);
        $groupIds = $items->pluck('group_id')->filter()->unique()->values()->all();

        return [
            'parent_key' => $parentKey,
            'parent_slug' => $this->identityBuilder->parentKeyToSlug($parentKey),
            'label' => $label,
            'item_type' => $itemType,
            'is_asset' => $itemType === ItemType::ASSET_LANCAR,
            'product_name' => $productName,
            'uses_placeholder' => $usesPlaceholder,
            'description' => $this->resolveDescription($items),
            'image_url' => $sample->group?->image_url ?? $sample->image_url,
            'group_ids' => $groupIds,
            'colors' => $colors,
            'total_warehouse_qty' => $items->sum(fn (Item $item) => $item->warehouseItems->sum('quantity')),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildParentSummaries(array $filters): Collection
    {
        /** @var array<string, array<string, mixed>> $parents */
        $parents = [];

        $this->listItemQuery()
            ->select([
                'id',
                'type',
                'code',
                'pcode',
                'group_id',
                'image_url',
            ])
            ->chunkById(300, function ($items) use (&$parents) {
                $warehouseQty = $this->warehouseQtyByItemId($items->pluck('id')->all());

                foreach ($items as $item) {
                    $parentKey = $this->identityBuilder->itemParentKey($item);

                    if (! isset($parents[$parentKey])) {
                        $parents[$parentKey] = [
                            'parent_key' => $parentKey,
                            'parent_slug' => $this->identityBuilder->parentKeyToSlug($parentKey),
                            'label' => $this->identityBuilder->itemParentLabel($item),
                            'product_name' => '',
                            'description' => '',
                            'image_url' => $item->group?->image_url ?? $item->image_url,
                            'is_asset' => $item->type === ItemType::ASSET_LANCAR,
                            'sku_count' => 0,
                            'in_warehouse_qty' => 0.0,
                            '_group_names' => [],
                            '_descriptions' => [],
                        ];
                    }

                    $parents[$parentKey]['sku_count']++;
                    $parents[$parentKey]['in_warehouse_qty'] += (float) ($warehouseQty[$item->id] ?? 0);

                    if ($item->group?->name) {
                        $parents[$parentKey]['_group_names'][$item->group->name] = true;
                    }

                    if ($item->group?->description) {
                        $parents[$parentKey]['_descriptions'][$item->group->description] = true;
                    }

                    if (! $parents[$parentKey]['image_url'] && $item->image_url) {
                        $parents[$parentKey]['image_url'] = $item->image_url;
                    }
                }
            });

        return collect($parents)
            ->map(function (array $parent) {
                $groupNames = array_keys($parent['_group_names']);
                $itemType = $parent['is_asset'] ? ItemType::ASSET_LANCAR : ItemType::ITEM;
                $parent['product_name'] = $this->resolveProductNameFromNames(
                    $groupNames,
                    $parent['parent_key'],
                    $itemType,
                );
                $parent['description'] = array_key_first($parent['_descriptions']) ?? '';
                unset($parent['_group_names'], $parent['_descriptions']);

                return $parent;
            })
            ->filter(function (array $parent) use ($filters) {
                if (! empty($filters['kode']) && ! str_contains(strtoupper($parent['label']), strtoupper($filters['kode']))) {
                    return false;
                }
                if (! empty($filters['product_name']) && ! str_contains(strtoupper($parent['product_name']), strtoupper($filters['product_name']))) {
                    return false;
                }
                if (! empty($filters['desc']) && ! str_contains(strtoupper($parent['description'] ?? ''), strtoupper($filters['desc']))) {
                    return false;
                }

                return true;
            })
            ->sortBy('label')
            ->values();
    }

    /**
     * @return Collection<int, Item>
     */
    protected function itemsForParentKey(string $parentKey): Collection
    {
        return $this->itemsQueryForParentKey($parentKey)
            ->get()
            ->filter(fn (Item $item) => $this->identityBuilder->itemParentKey($item) === $parentKey)
            ->values();
    }

    protected function itemsQueryForParentKey(string $parentKey): Builder
    {
        $parts = explode(':', $parentKey);
        $itemType = ItemType::from((int) ($parts[0] ?? ItemType::ITEM->value));

        $query = $this->detailItemQuery()->where('items.type', $itemType);

        if ($itemType === ItemType::ASSET_LANCAR) {
            $parentPcode = strtoupper($parts[1] ?? '');

            $query->where(function (Builder $q) use ($parentPcode) {
                $q->whereRaw('UPPER(items.pcode) = ?', [$parentPcode])
                    ->orWhereRaw('UPPER(items.code) LIKE ?', [$parentPcode.'-%']);
            });
        } else {
            $typeCode = strtoupper($parts[1] ?? '');
            $master = strtoupper($parts[2] ?? '');

            $query->where(function (Builder $q) use ($typeCode, $master) {
                $q->whereHas('group', fn (Builder $g) => $g->whereRaw('UPPER(master) = ?', [$master]))
                    ->where(function (Builder $inner) use ($typeCode) {
                        $inner->whereHas('tags', fn (Builder $t) => $t
                            ->where('tags.type', \App\Models\Tag::TYPE_TYPE)
                            ->whereRaw('UPPER(tags.code) = ?', [$typeCode]))
                            ->orWhereRaw('UPPER(items.code) LIKE ?', [$typeCode.'-%']);
                    });
            });
        }

        return $query;
    }

    protected function listItemQuery(): Builder
    {
        return Item::query()
            ->with([
                'group:id,master,variant,name,description,image_url',
                'tags:id,code,type,name',
            ])
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->whereNotNull('group_id');
    }

    protected function detailItemQuery(): Builder
    {
        return Item::query()
            ->with([
                'group',
                'tags',
                'warehouseItems' => fn ($q) => $q
                    ->whereIn('warehouse_id', fn ($sq) => $sq->select('id')->from('addrbooks')->whereIn('type', [
                        AddrbookType::Warehouse->value,
                        AddrbookType::VirtualWarehouse->value,
                    ]))
                    ->with('warehouse'),
            ])
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->whereNotNull('group_id');
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    protected function warehouseQtyByItemId(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::table('warehouse_items')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->whereIn('item_id', $itemIds)
            ->whereIn('warehouse_id', function ($query) {
                $query->select('id')
                    ->from('addrbooks')
                    ->whereIn('type', [
                        AddrbookType::Warehouse->value,
                        AddrbookType::VirtualWarehouse->value,
                    ]);
            })
            ->groupBy('item_id')
            ->pluck('total', 'item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    /**
     * @param  array<int, string>  $groupNames
     */
    protected function resolveProductNameFromNames(array $groupNames, string $parentKey, ItemType $itemType): string
    {
        $names = collect($groupNames)->filter()->unique()->values();

        if ($itemType === ItemType::ITEM) {
            $parts = explode(':', $parentKey);
            $master = strtoupper($parts[2] ?? '');

            $preferred = $names->first(
                fn (?string $name) => $name && strtoupper(trim($name)) !== $master
            );

            return $preferred ?? $names->first() ?? $master;
        }

        $parentLabel = explode(':', $parentKey, 2)[1] ?? '';

        $preferred = $names->first(
            fn (?string $name) => $name && strtoupper(trim($name)) !== strtoupper($parentLabel)
        );

        return $preferred ?? $names->first() ?? $parentLabel;
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  array<int, array<string, mixed>>  $jubelioStocks
     * @return list<array<string, mixed>>
     */
    protected function buildColorSections(Collection $items, array $jubelioStocks): array
    {
        $allSizeCodes = $this->orderedSizeCodes($items);
        $hasSizes = $allSizeCodes !== ['—'];

        return $items
            ->groupBy(fn (Item $item) => $this->identityBuilder->itemColorGroupKey($item))
            ->map(function (Collection $colorItems) use ($allSizeCodes, $hasSizes, $jubelioStocks) {
                $sample = $colorItems->first();
                $color = $this->identityBuilder->itemColorInfo($sample);

                $section = [
                    'code' => $color['code'],
                    'name' => $color['name'],
                    'pcode' => $sample->pcode,
                    'group_id' => $sample->group_id,
                    'has_sizes' => $hasSizes,
                    'size_rows' => [],
                    'no_size_items' => [],
                ];

                if ($hasSizes) {
                    foreach ($allSizeCodes as $sizeCode) {
                        $item = $colorItems->first(
                            fn (Item $i) => ($this->identityBuilder->itemSizeCode($i) ?? '—') === $sizeCode
                        );

                        if (! $item) {
                            continue;
                        }

                        $section['size_rows'][] = $this->buildSizeRow($item, $sizeCode, $jubelioStocks);
                    }
                } else {
                    foreach ($colorItems as $item) {
                        $section['no_size_items'][] = $this->buildSizeRow($item, '—', $jubelioStocks);
                    }
                }

                return $section;
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $jubelioStocks
     * @return array<string, mixed>
     */
    protected function buildSizeRow(Item $item, string $sizeCode, array $jubelioStocks): array
    {
        $base = $item->type === ItemType::ASSET_LANCAR ? '/assetlancar' : '/items';
        $jubelioId = (int) ($item->jubelio_item_id ?? 0);
        $jubelio = $jubelioId > 0 ? ($jubelioStocks[$jubelioId] ?? null) : null;

        return [
            'item_id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'size' => $sizeCode,
            'show_url' => $base.'/'.$item->id,
            'warehouse_qty' => $item->warehouseItems->sum('quantity'),
            'warehouses' => $item->warehouseItems->map(fn ($wh) => [
                'name' => $wh->warehouse?->name ?? 'Warehouse #'.$wh->warehouse_id,
                'quantity' => (float) $wh->quantity,
            ])->values()->all(),
            'jubelio' => $jubelio ? [
                'linked' => true,
                'on_hand' => (float) ($jubelio['total_stocks']['on_hand'] ?? 0),
                'on_order' => (float) ($jubelio['total_stocks']['on_order'] ?? 0),
                'reserved' => (float) ($jubelio['total_stocks']['reserved'] ?? 0),
                'available' => (float) ($jubelio['total_stocks']['available'] ?? 0),
            ] : [
                'linked' => false,
                'on_hand' => null,
                'on_order' => null,
                'reserved' => null,
                'available' => null,
            ],
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function resolveProductName(Collection $items, string $parentKey, ItemType $itemType): string
    {
        return $this->resolveProductNameFromNames(
            $items->map(fn (Item $item) => $item->group?->name)->filter()->unique()->values()->all(),
            $parentKey,
            $itemType,
        );
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function resolveDescription(Collection $items): string
    {
        return $items
            ->map(fn (Item $item) => $item->group?->description)
            ->filter()
            ->first() ?? '';
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return list<string>
     */
    protected function orderedSizeCodes(Collection $items): array
    {
        $codes = $items
            ->map(fn (Item $item) => $this->identityBuilder->itemSizeCode($item))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return ['—'];
        }

        return $codes
            ->sortBy(fn (string $code) => $this->sizeSortKey($code))
            ->values()
            ->all();
    }

    protected function sizeSortKey(string $code): string
    {
        $upper = strtoupper($code);
        $index = array_search($upper, self::SIZE_ORDER, true);

        if ($index !== false) {
            return '0_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'_'.$upper;
        }

        return '1_'.$upper;
    }
}
