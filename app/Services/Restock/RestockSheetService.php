<?php

namespace App\Services\Restock;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestockSheetService
{
    /** Manufactured-item TYPE tags use item_type = 2 and are excluded from restock. */
    public const EXCLUDED_TYPE_TAG_ITEM_TYPE = 2;

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * Asset-lancar TYPE tags only (excludes manufactured SKU-prefix tags).
     *
     * @return Collection<int, Tag>
     */
    public function typeTags(): Collection
    {
        return Tag::query()
            ->where('type', Tag::TYPE_TYPE)
            ->where('item_type', '!=', self::EXCLUDED_TYPE_TAG_ITEM_TYPE)
            ->orderBy('name')
            ->get();
    }

    /**
     * Summary for the TYPE landing page (one sheet per TYPE, e.g. BELT).
     *
     * @return array{
     *     type_tag: Tag,
     *     sheet: RestockSheet|null,
     *     parent_pcode_count: int,
     *     sku_count: int,
     *     totals: array{restock: int, production: int, shipped: int},
     *     urgent_count: int
     * }
     */
    public function typeSheetSummary(Tag $typeTag): array
    {
        $sheet = RestockSheet::query()
            ->where('type_tag_id', $typeTag->id)
            ->withSum('cells as total_restock', 'qty_restock')
            ->withSum('cells as total_production', 'qty_production')
            ->withSum('cells as total_shipped', 'qty_shipped')
            ->withCount(['cells as urgent_count' => fn ($q) => $q->where('is_urgent', true)])
            ->first();

        $itemsQuery = $this->assetLancarItemsForType($typeTag);

        return [
            'type_tag' => $typeTag,
            'sheet' => $sheet,
            'parent_pcode_count' => (int) (clone $itemsQuery)->distinct('items.pcode')->count('items.pcode'),
            'sku_count' => (int) (clone $itemsQuery)->count(),
            'totals' => [
                'restock' => (int) ($sheet?->total_restock ?? 0),
                'production' => (int) ($sheet?->total_production ?? 0),
                'shipped' => (int) ($sheet?->total_shipped ?? 0),
            ],
            'urgent_count' => (int) ($sheet?->urgent_count ?? 0),
        ];
    }

    public function canCreateSheetForType(Tag $typeTag): bool
    {
        if (RestockSheet::where('type_tag_id', $typeTag->id)->exists()) {
            return false;
        }

        return $this->assetLancarItemsForType($typeTag)->exists();
    }

    public function createSheet(Tag $typeTag, User $user): RestockSheet
    {
        if (RestockSheet::where('type_tag_id', $typeTag->id)->exists()) {
            throw new InvalidArgumentException("A restock sheet already exists for {$typeTag->name}.");
        }

        $items = $this->assetLancarItemsForType($typeTag)
            ->with(['tags', 'group'])
            ->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException("No asset lancar items found for TYPE {$typeTag->code}.");
        }

        return DB::transaction(function () use ($typeTag, $user, $items) {
            $sheet = RestockSheet::create([
                'name' => $typeTag->name,
                'type_tag_id' => $typeTag->id,
                'representative_group_id' => $this->resolveRepresentativeGroupId($items),
                'created_by' => $user->id,
            ]);

            $this->seedCells($sheet, $items);

            return $sheet->fresh(['cells.item.tags', 'typeTag', 'representativeGroup']);
        });
    }

    /**
     * @return int Number of cells added
     */
    public function syncSkus(RestockSheet $sheet): int
    {
        $items = $this->assetLancarItemsForType($sheet->typeTag)
            ->with('tags')
            ->get();

        $existingItemIds = $sheet->cells()->pluck('item_id');

        $newItems = $items->reject(fn (Item $item) => $existingItemIds->contains($item->id));

        if ($newItems->isEmpty()) {
            return 0;
        }

        $this->seedCells($sheet, $newItems);

        return $newItems->count();
    }

    /**
     * Cells grouped by parent pcode (BELT-01, BELT-02, …) for sheet display.
     *
     * @return Collection<string, Collection<int, RestockCell>>
     */
    public function cellsGroupedByParent(RestockSheet $sheet): Collection
    {
        $sheet->loadMissing(['cells.color', 'cells.size', 'cells.item']);

        return $sheet->cells
            ->sortBy([
                fn (RestockCell $cell) => $cell->item?->pcode ?? '',
                fn (RestockCell $cell) => $cell->color?->name ?? '',
                fn (RestockCell $cell) => $cell->size?->name ?? '',
            ])
            ->groupBy(fn (RestockCell $cell) => $cell->item?->pcode ?? 'unknown');
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function seedCells(RestockSheet $sheet, Collection $items): void
    {
        foreach ($items as $item) {
            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);

            RestockCell::firstOrCreate(
                [
                    'restock_sheet_id' => $sheet->id,
                    'item_id' => $item->id,
                ],
                [
                    'color_id' => $warnaTag?->id,
                    'size_id' => $sizeTag && ! $this->identityBuilder->isAllSize($sizeTag) ? $sizeTag->id : null,
                    'urgent_threshold' => $item->restock_urgent_threshold,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function resolveRepresentativeGroupId(Collection $items): ?int
    {
        $groupId = $items->first(fn (Item $item) => $item->group_id > 0)?->group_id;

        return $groupId > 0 ? $groupId : null;
    }

    protected function assetLancarItemsForType(Tag $typeTag)
    {
        return Item::query()
            ->where('type', ItemType::ASSET_LANCAR)
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $typeTag->id));
    }
}
