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
    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * @return Collection<int, Tag>
     */
    public function typeTags(): Collection
    {
        return Tag::query()
            ->where('type', Tag::TYPE_TYPE)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, array{pcode: string, name: string, representative_group_id: int|null, sheet: RestockSheet|null, totals: array<string, int>, urgent_count: int}>
     */
    public function parentsForType(Tag $typeTag): Collection
    {
        $pcodes = $this->assetLancarItemsForType($typeTag)
            ->select('items.pcode')
            ->distinct()
            ->pluck('pcode');

        $sheets = RestockSheet::query()
            ->where('type_tag_id', $typeTag->id)
            ->whereIn('pcode', $pcodes)
            ->withSum('cells as total_restock', 'qty_restock')
            ->withSum('cells as total_production', 'qty_production')
            ->withSum('cells as total_shipped', 'qty_shipped')
            ->withCount(['cells as urgent_count' => fn ($q) => $q->where('is_urgent', true)])
            ->get()
            ->keyBy('pcode');

        return $pcodes->map(function (string $pcode) use ($typeTag, $sheets) {
            $sampleItem = $this->assetLancarItemsForType($typeTag)
                ->where('items.pcode', $pcode)
                ->with('group')
                ->first();

            $sheet = $sheets->get($pcode);

            return [
                'pcode' => $pcode,
                'name' => $sheet?->name ?? $sampleItem?->group?->name ?? $pcode,
                'representative_group_id' => $sheet?->representative_group_id ?? $sampleItem?->group_id,
                'sheet' => $sheet,
                'totals' => [
                    'restock' => (int) ($sheet?->total_restock ?? 0),
                    'production' => (int) ($sheet?->total_production ?? 0),
                    'shipped' => (int) ($sheet?->total_shipped ?? 0),
                ],
                'urgent_count' => (int) ($sheet?->urgent_count ?? 0),
            ];
        })->sortBy('pcode')->values();
    }

    /**
     * @return Collection<int, array{pcode: string, name: string}>
     */
    public function availableParentsForType(Tag $typeTag): Collection
    {
        $existingPcodes = RestockSheet::query()
            ->where('type_tag_id', $typeTag->id)
            ->pluck('pcode');

        return $this->assetLancarItemsForType($typeTag)
            ->with('group')
            ->get()
            ->groupBy('pcode')
            ->reject(fn ($items, string $pcode) => $existingPcodes->contains($pcode))
            ->map(fn ($items, string $pcode) => [
                'pcode' => $pcode,
                'name' => $items->first()->group?->name ?? $pcode,
            ])
            ->sortBy('pcode')
            ->values();
    }

    public function createSheet(Tag $typeTag, string $pcode, User $user): RestockSheet
    {
        $pcode = strtoupper(trim($pcode));

        if (RestockSheet::where('pcode', $pcode)->exists()) {
            throw new InvalidArgumentException("A restock sheet already exists for {$pcode}.");
        }

        $items = $this->assetLancarItemsForType($typeTag)
            ->where('items.pcode', $pcode)
            ->with(['tags', 'group'])
            ->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException("No asset lancar items found for {$pcode} with TYPE {$typeTag->code}.");
        }

        $untagged = $items->filter(fn (Item $item) => ! $this->itemHasTypeTag($item, $typeTag));

        if ($untagged->isNotEmpty()) {
            throw new InvalidArgumentException(
                'All items must have the selected TYPE tag before creating a restock sheet.'
            );
        }

        $name = $items->first()->group?->name ?? $pcode;

        return DB::transaction(function () use ($typeTag, $pcode, $user, $items, $name) {
            $sheet = RestockSheet::create([
                'pcode' => $pcode,
                'name' => $name,
                'type_tag_id' => $typeTag->id,
                'representative_group_id' => $items->first()->group_id,
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
            ->where('items.pcode', $sheet->pcode)
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

    protected function assetLancarItemsForType(Tag $typeTag)
    {
        return Item::query()
            ->where('type', ItemType::ASSET_LANCAR)
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $typeTag->id));
    }

    protected function itemHasTypeTag(Item $item, Tag $typeTag): bool
    {
        return $item->tags->contains('id', $typeTag->id);
    }
}
