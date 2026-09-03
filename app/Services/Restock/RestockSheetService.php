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
  /** Asset lancar TYPE tags: tags.type = TYPE_TYPE (3) and tags.item_type = ASSET_LANCAR (2). */
  public const ASSET_LANCAR_TYPE_TAG_ITEM_TYPE = 2;

  public function __construct(
    protected ItemIdentityBuilder $identityBuilder,
  ) {}

  public static function isAssetLancarTypeTag(Tag $tag): bool
  {
    return (int) $tag->type === Tag::TYPE_TYPE
      && (int) $tag->item_type === self::ASSET_LANCAR_TYPE_TAG_ITEM_TYPE;
  }

  /**
   * @return Collection<int, Tag>
   */
  public function typeTags(): Collection
  {
    return Tag::query()
      ->where('type', Tag::TYPE_TYPE)
      ->where('item_type', ItemType::ASSET_LANCAR->value)
      ->orderBy('name')
      ->get();
  }

  /**
   * Per-sheet pipeline totals for the restock front page.
   *
   * @return Collection<int, array{id: int, name: string, qty_restock: int, qty_production: int, qty_shipped: int}>
   */
  public function sheetSummaries(): Collection
  {
    return RestockSheet::query()
      ->withSum('cells as qty_restock_total', 'qty_restock')
      ->withSum('cells as qty_production_total', 'qty_production')
      ->withSum('cells as qty_shipped_total', 'qty_shipped')
      ->orderBy('name')
      ->get()
      ->map(fn (RestockSheet $sheet) => [
        'id' => $sheet->id,
        'name' => $sheet->name,
        'qty_restock' => (int) $sheet->qty_restock_total,
        'qty_production' => (int) $sheet->qty_production_total,
        'qty_shipped' => (int) $sheet->qty_shipped_total,
      ])
      ->values();
  }

  /**
   * Parent pcode rows for the TYPE landing page (BELT-01, BELT-02, …).
   *
   * @return Collection<int, array{pcode: string, name: string, image_url: string, sku_count: int, totals: array{restock: int, production: int, shipped: int}, urgent_count: int}>
   */
  public function parentsForType(Tag $typeTag): Collection
  {
    $sheet = RestockSheet::query()
      ->where('type_tag_id', $typeTag->id)
      ->with(['cells.item.group'])
      ->first();

    $itemsByParent = $this->assetLancarItemsForType($typeTag)
      ->with(['group', 'tags'])
      ->get()
      ->groupBy(fn (Item $item) => $this->identityBuilder->assetLancarParentPcode($item));

    $cellsByParent = $sheet
      ? $sheet->cells
        ->filter(fn (RestockCell $cell) => $cell->item !== null)
        ->groupBy(fn (RestockCell $cell) => $this->identityBuilder->assetLancarParentPcode($cell->item))
      : collect();

    return $itemsByParent
      ->map(function (Collection $items, string $parentPcode) use ($cellsByParent) {
        $cells = $cellsByParent->get($parentPcode, collect());
        $group = $items->first()?->group;
        $name = $items->pluck('group.name')
          ->filter(fn (?string $n) => $n && strtoupper(trim($n)) !== strtoupper($parentPcode))
          ->first() ?? $group?->name ?? $parentPcode;

        return [
          'pcode' => $parentPcode,
          'name' => $name,
          'image_url' => $group?->image_url ?? asset('images/default-item.svg'),
          'sku_count' => $items->count(),
          'totals' => [
            'restock' => (int) $cells->sum('qty_restock'),
            'production' => (int) $cells->sum('qty_production'),
            'shipped' => (int) $cells->sum('qty_shipped'),
            'missing' => (int) $cells->sum('qty_missing'),
          ],
          'urgent_count' => (int) $cells->where('is_urgent', true)->count(),
        ];
      })
      ->sortBy('pcode')
      ->values();
  }

  public function sheetForType(Tag $typeTag): ?RestockSheet
  {
    return RestockSheet::query()
      ->where('type_tag_id', $typeTag->id)
      ->first();
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
   * @return Collection<string, Collection<int, RestockCell>>
   */
  public function cellsGroupedByParent(RestockSheet $sheet): Collection
  {
    $sheet->loadMissing(['cells.color', 'cells.size', 'cells.item']);

    return $sheet->cells
      ->filter(fn (RestockCell $cell) => $cell->item !== null)
      ->sortBy([
        fn (RestockCell $cell) => $this->identityBuilder->assetLancarParentPcode($cell->item),
        fn (RestockCell $cell) => $cell->color?->name ?? '',
        fn (RestockCell $cell) => $cell->size?->name ?? '',
      ])
      ->groupBy(fn (RestockCell $cell) => $this->identityBuilder->assetLancarParentPcode($cell->item));
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
