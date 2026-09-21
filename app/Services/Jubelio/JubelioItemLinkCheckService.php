<?php

namespace App\Services\Jubelio;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\JubelioItemLinkAttempt;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Jubelio\JubelioItemAutoLinkService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JubelioItemLinkCheckService
{
    public function __construct(
        protected ItemGroupHierarchyService $groupHierarchy,
    ) {}

    public static function isLinked(?int $jubelioItemId): bool
    {
        return (int) ($jubelioItemId ?? 0) > 0;
    }

    /**
     * @param  array{kode?: string, product_name?: string, desc?: string, link?: string}  $filters
     */
    public function paginateParentGroups(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->groupHierarchy->paginateParents($filters, $perPage);

        $paginator->setCollection(
            collect($paginator->items())->map(function (array $parent) {
                $stats = $this->linkStatsForParentGroupId($parent['parent_group_id']);

                return array_merge($parent, [
                    'linked_sku_count' => $stats['linked'],
                    'unlinked_sku_count' => $stats['unlinked'],
                    'total_sku_count' => $stats['total'],
                ]);
            })
        );

        return $paginator;
    }

    /**
     * @return array{linked: int, unlinked: int, total: int}
     */
    public function linkStatsForParentGroupId(int $parentGroupId): array
    {
        $group = ItemGroup::query()->find($parentGroupId);
        if ($group === null) {
            return ['linked' => 0, 'unlinked' => 0, 'total' => 0];
        }

        $itemIds = $this->itemIdsForParentAnchor($group);
        if ($itemIds->isEmpty()) {
            return ['linked' => 0, 'unlinked' => 0, 'total' => 0];
        }

        $linked = Item::query()
            ->whereIn('id', $itemIds)
            ->whereNull('deleted_at')
            ->where('jubelio_item_id', '>', 0)
            ->count();

        $total = $itemIds->count();

        return [
            'linked' => $linked,
            'unlinked' => max(0, $total - $linked),
            'total' => $total,
        ];
    }

    /**
     * @return list<array{
     *     item_id: int,
     *     code: string,
     *     name: string,
     *     size: string,
     *     color_code: string,
     *     color_name: string,
     *     jubelio_item_id: int|null,
     *     linked: bool,
     *     jubelio_url: string,
     *     show_url: string,
     * }>
     */
    public function skuRowsForParentAnchor(ItemGroup $group): array
    {
        $detail = $this->groupHierarchy->parentDetailForAnchorGroup($group, fetchJubelio: false);
        if ($detail === null) {
            return [];
        }

        $itemIds = collect($detail['colors'])
            ->flatMap(function (array $color) {
                $itemRows = $color['has_sizes'] ? $color['size_rows'] : $color['no_size_items'];

                return collect($itemRows)->pluck('item_id');
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $itemsById = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'jubelio_item_id'])
            ->keyBy('id');

        $rows = [];

        foreach ($detail['colors'] as $color) {
            $itemRows = $color['has_sizes'] ? $color['size_rows'] : $color['no_size_items'];

            foreach ($itemRows as $row) {
                $item = $itemsById->get($row['item_id']);
                $jubelioId = $item ? (int) ($item->jubelio_item_id ?? 0) : 0;
                $linked = self::isLinked($jubelioId > 0 ? $jubelioId : null);

                $rows[] = [
                    'item_id' => (int) $row['item_id'],
                    'code' => (string) $row['code'],
                    'name' => (string) $row['name'],
                    'size' => (string) $row['size'],
                    'color_code' => (string) ($color['code'] ?? ''),
                    'color_name' => (string) ($color['name'] ?? ''),
                    'jubelio_item_id' => $linked ? $jubelioId : null,
                    'linked' => $linked,
                    'jubelio_url' => route('items.jubelio', $row['item_id']),
                    'show_url' => (string) ($row['show_url'] ?? '/items/'.$row['item_id']),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array{q?: string, link?: string}  $filters
     */
    public function paginateItems(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $linkFilter = (string) ($filters['link'] ?? 'all');

        return Item::query()
            ->with(['latestJubelioLinkAttempt'])
            ->whereNull('deleted_at')
            ->whereIn('type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('legacy_code', 'like', '%'.$search.'%');
                });
            })
            ->when($linkFilter === 'linked', fn (Builder $q) => $q->where('jubelio_item_id', '>', 0))
            ->when($linkFilter === 'unlinked', function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNull('jubelio_item_id')
                        ->orWhere('jubelio_item_id', '<=', 0);
                });
            })
            ->when($linkFilter === 'auto_failed', function (Builder $q) {
                $q->whereNull('jubelio_item_id')
                    ->whereRaw(
                        '(select count(*) from jubelio_item_link_attempts where jubelio_item_link_attempts.item_id = items.id and outcome in (?, ?, ?)) >= ?',
                        [
                            JubelioItemLinkAttempt::OUTCOME_NO_MATCH,
                            JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS,
                            JubelioItemLinkAttempt::OUTCOME_API_ERROR,
                            JubelioItemAutoLinkService::MAX_ATTEMPTS,
                        ],
                    );
            })
            ->when($linkFilter === 'ambiguous', function (Builder $q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('jubelio_item_link_attempts as latest_amb')
                        ->whereColumn('latest_amb.item_id', 'items.id')
                        ->where('latest_amb.outcome', JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS)
                        ->whereRaw(
                            'latest_amb.id = (select max(id) from jubelio_item_link_attempts where item_id = items.id)',
                        );
                });
            })
            ->when($linkFilter === 'auto_linked', function (Builder $q) {
                $q->where('jubelio_item_id', '>', 0)
                    ->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('jubelio_item_link_attempts')
                            ->whereColumn('jubelio_item_link_attempts.item_id', 'items.id')
                            ->where('outcome', JubelioItemLinkAttempt::OUTCOME_LINKED);
                    });
            })
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Item $item) {
                $linked = self::isLinked($item->jubelio_item_id);
                $latestAttempt = $item->relationLoaded('latestJubelioLinkAttempt')
                    ? $item->latestJubelioLinkAttempt
                    : null;

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'type' => $item->type,
                    'jubelio_item_id' => $linked ? (int) $item->jubelio_item_id : null,
                    'linked' => $linked,
                    'auto_link_outcome' => $latestAttempt?->outcome,
                    'auto_link_checked_at' => $latestAttempt?->created_at?->toDateTimeString(),
                    'show_url' => $item->showUrl(),
                    'jubelio_url' => route('items.jubelio', $item->id),
                ];
            });
    }

    /**
     * @return Collection<int, int>
     */
    protected function itemIdsForParentAnchor(ItemGroup $group): Collection
    {
        $detail = $this->groupHierarchy->parentDetailForAnchorGroup($group, fetchJubelio: false);
        if ($detail === null) {
            return collect();
        }

        return collect($detail['colors'])
            ->flatMap(function (array $color) {
                $itemRows = $color['has_sizes'] ? $color['size_rows'] : $color['no_size_items'];

                return collect($itemRows)->pluck('item_id');
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
