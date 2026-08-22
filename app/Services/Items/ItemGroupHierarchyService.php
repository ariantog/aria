<?php

namespace App\Services\Items;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
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
        $query = ItemGroup::query()
            ->select([
                'item_group.master',
                DB::raw('MIN(item_group.name) as product_name'),
                DB::raw('MIN(item_group.description) as description'),
                DB::raw('MIN(item_group.id) as sample_group_id'),
                DB::raw('COUNT(DISTINCT item_group.id) as variant_count'),
                DB::raw('COUNT(DISTINCT items.id) as sku_count'),
            ])
            ->leftJoin('items', function ($join) {
                $join->on('items.group_id', '=', 'item_group.id')
                    ->whereNull('items.deleted_at');
            })
            ->whereNotNull('item_group.master')
            ->where('item_group.master', '!=', '')
            ->when(! empty($filters['kode']), fn (Builder $q) => $q->where(
                'item_group.master',
                'like',
                '%'.$filters['kode'].'%'
            ))
            ->when(! empty($filters['product_name']), fn (Builder $q) => $q->where(
                'item_group.name',
                'like',
                '%'.$filters['product_name'].'%'
            ))
            ->when(! empty($filters['desc']), fn (Builder $q) => $q->where(
                'item_group.description',
                'like',
                '%'.$filters['desc'].'%'
            ))
            ->groupBy('item_group.master')
            ->orderBy('item_group.master');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $sampleGroupIds = collect($paginator->items())->pluck('sample_group_id')->filter()->all();
        $samplesByGroupId = $this->sampleItemsByGroupId($sampleGroupIds);

        $paginator->setCollection(
            collect($paginator->items())->map(function ($row) use ($samplesByGroupId) {
                $master = (string) $row->master;
                $sample = $samplesByGroupId[$row->sample_group_id] ?? null;
                $isAsset = $this->isAssetMaster($master, $sample);
                $parentKey = $this->parentKeyForMaster($master, $sample, $isAsset);
                $label = $isAsset
                    ? $master
                    : trim(($sample ? $this->identityBuilder->manufacturedTypeCode($sample) : 'UNK').' '.$master);

                return [
                    'parent_key' => $parentKey,
                    'parent_slug' => $this->identityBuilder->parentKeyToSlug($parentKey),
                    'label' => $label,
                    'product_name' => $this->resolveListProductName(
                        (string) ($row->product_name ?? ''),
                        $master,
                        $isAsset,
                    ),
                    'description' => (string) ($row->description ?? ''),
                    'is_asset' => $isAsset,
                    'sku_count' => (int) $row->sku_count,
                    'variant_count' => (int) $row->variant_count,
                ];
            })
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parentDetail(string $parentKey, bool $fetchJubelio = true): ?array
    {
        $groups = $this->groupsForParentKey($parentKey);
        if ($groups->isEmpty()) {
            return null;
        }

        $items = $groups->flatMap(fn (ItemGroup $group) => $group->items);
        if ($items->isEmpty()) {
            return null;
        }

        $sample = $items->first();
        $itemType = $sample->type;
        $label = $this->identityBuilder->itemParentLabel($sample);
        $productName = $this->resolveProductName($groups, $parentKey, $itemType);
        $usesPlaceholder = $itemType === ItemType::ITEM
            && $productName !== ''
            && strtoupper($productName) === strtoupper($this->identityBuilder->manufacturedParentMaster($sample));

        $jubelioStocks = $fetchJubelio
            ? $this->jubelioService->fetchItemStocks(
                $items->pluck('jubelio_item_id')->filter(fn ($id) => (int) $id > 0)->unique()->values()->all()
            )
            : [];

        $colors = $this->buildColorSectionsFromGroups($groups, $jubelioStocks, $items);
        $warehouseBreakdown = $this->aggregateWarehouseBreakdown($items);

        $warehouseNames = array_column($warehouseBreakdown, 'name');

        return [
            'parent_key' => $parentKey,
            'parent_slug' => $this->identityBuilder->parentKeyToSlug($parentKey),
            'label' => $label,
            'item_type' => $itemType,
            'is_asset' => $itemType === ItemType::ASSET_LANCAR,
            'product_name' => $productName,
            'uses_placeholder' => $usesPlaceholder,
            'description' => $this->resolveDescription($groups),
            'image_url' => $groups->first()?->image_url,
            'group_ids' => $groups->pluck('id')->all(),
            'colors' => $colors,
            'total_warehouse_qty' => $this->sumWarehouseBreakdown($warehouseBreakdown),
            'warehouse_breakdown' => $warehouseBreakdown,
            'warehouse_names' => $warehouseNames,
        ];
    }

    /**
     * @return array{label: string, warehouse_names: list<string>, rows: list<array<string, mixed>>}|null
     */
    public function exportPayload(string $parentKey): ?array
    {
        $detail = $this->parentDetail($parentKey);
        if ($detail === null) {
            return null;
        }

        $warehouseNames = $detail['warehouse_names'];
        $rows = [];

        foreach ($detail['colors'] as $color) {
            $itemRows = $color['has_sizes'] ? $color['size_rows'] : $color['no_size_items'];

            foreach ($itemRows as $row) {
                $warehouseQtys = array_fill_keys($warehouseNames, 0.0);

                foreach ($row['warehouses'] as $warehouse) {
                    $warehouseQtys[$warehouse['name']] = (float) $warehouse['quantity'];
                }

                $rows[] = [
                    'item_id' => $row['item_id'],
                    'item_name' => $row['name'],
                    'item_code' => $row['code'],
                    'color_code' => $color['code'],
                    'color_name' => $color['name'],
                    'color_pcode' => $color['pcode'] ?? '',
                    'size' => $row['size'],
                    'warehouse_qtys' => $warehouseQtys,
                    'aria_total' => (float) $row['warehouse_qty'],
                    'jubelio_on_hand' => $row['jubelio']['linked'] ? (float) $row['jubelio']['on_hand'] : null,
                    'jubelio_available' => $row['jubelio']['linked'] ? (float) $row['jubelio']['available'] : null,
                ];
            }
        }

        return [
            'label' => $detail['label'],
            'warehouse_names' => $warehouseNames,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return list<array{name: string, quantity: float}>
     */
    protected function aggregateWarehouseBreakdown(Collection $items): array
    {
        $totals = [];

        foreach ($items as $item) {
            foreach ($item->warehouseItems as $warehouseItem) {
                $name = $warehouseItem->warehouse?->name ?? 'Warehouse #'.$warehouseItem->warehouse_id;
                $totals[$name] = ($totals[$name] ?? 0) + (float) $warehouseItem->quantity;
            }
        }

        ksort($totals);

        return collect($totals)
            ->map(fn (float $quantity, string $name) => [
                'name' => $name,
                'quantity' => $quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, quantity: float}>  $breakdown
     */
    protected function sumWarehouseBreakdown(array $breakdown): float
    {
        return array_sum(array_column($breakdown, 'quantity'));
    }

    /**
     * @return Collection<int, ItemGroup>
     */
    protected function groupsForParentKey(string $parentKey): Collection
    {
        $parts = explode(':', $parentKey);
        $itemType = ItemType::from((int) ($parts[0] ?? ItemType::ITEM->value));

        $query = ItemGroup::query()
            ->with([
                'items' => fn ($q) => $q
                    ->where('type', $itemType)
                    ->whereNull('deleted_at')
                    ->with([
                        'tags',
                        'warehouseItems' => fn ($wq) => $wq
                            ->whereIn('warehouse_id', fn ($sq) => $sq->select('id')->from('customers')->whereIn('type', [
                                AddrbookType::Warehouse->value,
                                AddrbookType::VirtualWarehouse->value,
                            ]))
                            ->with('warehouse'),
                    ]),
            ])
            ->whereHas('items', fn (Builder $q) => $q->where('type', $itemType)->whereNull('deleted_at'));

        if ($itemType === ItemType::ASSET_LANCAR) {
            $master = strtoupper($parts[1] ?? '');
            $query->whereRaw('UPPER(item_group.master) = ?', [$master]);
        } else {
            $typeCode = strtoupper($parts[1] ?? '');
            $master = strtoupper($parts[2] ?? '');
            $query->whereRaw('UPPER(item_group.master) = ?', [$master])
                ->whereHas('items', function (Builder $q) use ($typeCode) {
                    $q->where(function (Builder $inner) use ($typeCode) {
                        $inner->whereHas('tags', fn (Builder $t) => $t
                            ->where('tags.type', Tag::TYPE_TYPE)
                            ->whereRaw('UPPER(tags.code) = ?', [$typeCode]))
                            ->orWhereRaw('UPPER(items.code) LIKE ?', [$typeCode.'-%']);
                    });
                });
        }

        return $query->orderBy('variant')->get();
    }

    /**
     * @param  list<int>  $groupIds
     * @return array<int, Item>
     */
    protected function sampleItemsByGroupId(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Item::query()
            ->whereIn('group_id', $groupIds)
            ->whereNull('deleted_at')
            ->with(['tags' => fn ($q) => $q->where('type', Tag::TYPE_TYPE)])
            ->orderBy('id')
            ->get()
            ->unique('group_id')
            ->keyBy('group_id')
            ->all();
    }

    protected function isAssetMaster(string $master, ?Item $sample): bool
    {
        if ($sample) {
            return $sample->type === ItemType::ASSET_LANCAR;
        }

        return (bool) preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9]+$/', $master)
            && ! preg_match('/^[A-Z]{2,3}[0-9]{5}$/i', $master);
    }

    protected function parentKeyForMaster(string $master, ?Item $sample, bool $isAsset): string
    {
        if ($isAsset) {
            return ItemType::ASSET_LANCAR->value.':'.strtoupper($master);
        }

        $typeCode = $sample
            ? $this->identityBuilder->manufacturedTypeCode($sample)
            : 'UNK';

        return ItemType::ITEM->value.':'.strtoupper($typeCode).':'.strtoupper($master);
    }

    protected function resolveListProductName(string $name, string $master, bool $isAsset): string
    {
        $name = trim($name);
        $masterUpper = strtoupper($master);

        if ($name === '' || strtoupper($name) === $masterUpper) {
            return $isAsset ? $master : '';
        }

        return $name;
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     * @param  array<int, array<string, mixed>>  $jubelioStocks
     * @return list<array<string, mixed>>
     */
    protected function buildColorSectionsFromGroups(
        Collection $groups,
        array $jubelioStocks,
        Collection $allItems,
    ): array {
        $allSizeCodes = $this->orderedSizeCodes($allItems);
        $hasSizes = $allSizeCodes !== ['—'];

        return $groups
            ->map(function (ItemGroup $group) use ($allSizeCodes, $hasSizes, $jubelioStocks) {
                $colorItems = $group->items;
                $sample = $colorItems->first();

                if (! $sample) {
                    return null;
                }

                $color = $this->identityBuilder->itemColorInfo($sample);

                $section = [
                    'code' => $color['code'],
                    'name' => $color['name'],
                    'pcode' => $sample->pcode,
                    'group_id' => $group->id,
                    'has_sizes' => $hasSizes,
                    'warehouse_breakdown' => $this->aggregateWarehouseBreakdown($colorItems),
                    'in_warehouse_qty' => $colorItems->sum(fn (Item $item) => $item->warehouseItems->sum('quantity')),
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
            ->filter()
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
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function resolveProductName(Collection $groups, string $parentKey, ItemType $itemType): string
    {
        return $this->resolveProductNameFromNames(
            $groups->map(fn (ItemGroup $group) => $group->name)->filter()->unique()->values()->all(),
            $parentKey,
            $itemType,
        );
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
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function resolveDescription(Collection $groups): string
    {
        return $groups
            ->map(fn (ItemGroup $group) => $group->description)
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
