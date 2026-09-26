<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Services\ItemService;
use App\Support\ItemProductTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild items.name to match legacy L10-style display names after SKU identity conversion.
 */
class ItemLegacyDisplayNameService
{
    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
        protected ItemGroupHierarchyService $hierarchy,
        protected ItemService $itemService,
    ) {}

    /**
     * @return list<string>
     */
    public function parentKeysForNeedle(string $needle, ItemType $type = ItemType::ITEM): array
    {
        $needle = strtoupper(trim($needle));
        if ($needle === '') {
            return [];
        }

        $keys = [];
        Item::query()
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($needle) {
                $query->whereRaw('UPPER(TRIM(pcode)) LIKE ?', [$needle.'%'])
                    ->orWhereRaw('UPPER(TRIM(code)) LIKE ?', ['%'.$needle.'%']);
            })
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$keys): void {
                foreach ($rows as $row) {
                    $item = Item::query()->with(['group', 'tags'])->find($row->id);
                    if ($item === null) {
                        continue;
                    }
                    $keys[$this->identityBuilder->itemParentKey($item)] = true;
                }
            });

        return array_keys($keys);
    }

    /**
     * @return array{
     *     parent_key: string,
     *     label: string,
     *     inferred_title: string|null,
     *     item_count: int,
     *     would_change: int,
     *     rows: list<array{id: int, code: string, current: string, proposed: string, changes: bool}>
     * }
     */
    public function previewForParentKey(string $parentKey): array
    {
        $items = $this->itemsForParentKey($parentKey);
        $sample = $items->first();
        $label = $sample !== null
            ? $this->identityBuilder->itemParentLabel($sample)
            : $parentKey;

        $inferred = $this->inferProductTitleFromItems($items);
        $rows = [];
        $wouldChange = 0;

        foreach ($items as $item) {
            $proposed = $this->proposedDisplayName($item, $inferred);
            $current = trim((string) $item->name);
            $changes = strtoupper($current) !== strtoupper($proposed);
            if ($changes) {
                $wouldChange++;
            }
            $rows[] = [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'current' => $current,
                'proposed' => $proposed,
                'changes' => $changes,
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['code'] <=> $b['code']);

        return [
            'parent_key' => $parentKey,
            'label' => $label,
            'inferred_title' => $inferred,
            'item_count' => $items->count(),
            'would_change' => $wouldChange,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{updated: int, product_title: string|null, parent_key: string}
     */
    public function applyForParentKey(string $parentKey, ?string $productTitle = null): array
    {
        $items = $this->itemsForParentKey($parentKey);
        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('No items found for this product group.');
        }

        $productTitle = strtoupper(trim($productTitle ?? ''));
        if ($productTitle === '') {
            $inferred = $this->inferProductTitleFromItems($items);
            if ($inferred === null || $inferred === '') {
                throw new \InvalidArgumentException(
                    'Could not infer a product title from legacy names. Enter one manually (e.g. CORE SHORTS).',
                );
            }
            $productTitle = $inferred;
        }

        $groupIds = $this->hierarchy->groupIdsForParentKey($parentKey);
        $itemCount = $items->count();

        DB::transaction(function () use ($parentKey, $productTitle, $groupIds): void {
            ItemProductTitle::syncParentProductName($parentKey, $productTitle);

            foreach ($groupIds as $groupId) {
                $group = ItemGroup::query()->find($groupId);
                if ($group === null) {
                    continue;
                }

                $sample = Item::query()->where('group_id', $groupId)->first();
                if ($sample === null) {
                    continue;
                }

                $itemType = ItemType::coerce($sample->type) ?? ItemType::ITEM;
                $pcode = strtoupper(trim((string) $sample->pcode));
                $group->name = $this->identityBuilder->uniqueStoredGroupName(
                    $this->identityBuilder->storedGroupName(
                        $itemType,
                        '',
                        $pcode,
                        (string) ($group->variant ?? ''),
                    ),
                    (string) ($group->master ?? ''),
                    (string) ($group->variant ?? ''),
                );
                $group->save();

                $this->itemService->rebuildDisplayNamesForGroup($group->fresh());
            }
        });

        return [
            'updated' => $itemCount,
            'product_title' => $productTitle,
            'parent_key' => $parentKey,
        ];
    }

    public function proposedDisplayName(Item $item, ?string $bareProductTitle = null): string
    {
        $item->loadMissing(['group', 'tags']);

        if ($bareProductTitle !== null && trim($bareProductTitle) !== '') {
            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
            if (! $sizeTag && (int) $item->size > 0) {
                $sizeTag = Tag::find((int) $item->size);
            }

            return $this->identityBuilder->buildName(
                strtoupper(trim($bareProductTitle)),
                $warnaTag,
                $sizeTag,
            );
        }

        return ItemProductTitle::buildDisplayName($item);
    }

    /**
     * @return Collection<int, Item>
     */
    public function itemsForParentKey(string $parentKey): Collection
    {
        $groupIds = $this->hierarchy->groupIdsForParentKey($parentKey);
        if ($groupIds === []) {
            return collect();
        }

        return Item::query()
            ->whereIn('group_id', $groupIds)
            ->whereNull('deleted_at')
            ->with(['group', 'tags'])
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    public function inferProductTitleFromItems(Collection $items): ?string
    {
        $counts = [];

        foreach ($items as $item) {
            $title = $this->parseBareTitleFromLegacyName((string) $item->name, $item);
            if ($title === null) {
                continue;
            }
            $counts[$title] = ($counts[$title] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    public function parseBareTitleFromLegacyName(string $name, Item $item): ?string
    {
        $name = trim($name);
        if ($name === '' || ! str_contains($name, ' - ')) {
            return null;
        }

        $parts = array_map('trim', explode(' - ', $name));
        $first = strtoupper($parts[0] ?? '');
        if ($first === '' || count($parts) < 2) {
            return null;
        }

        if ($this->looksLikeTechnicalTitlePrefix($first, $item)) {
            return null;
        }

        return $first;
    }

    protected function looksLikeTechnicalTitlePrefix(string $firstSegment, Item $item): bool
    {
        if (preg_match('/^[A-Z]{2,3}\s+[A-Z0-9\/\-]+$/', $firstSegment)) {
            return true;
        }

        $typeCode = $this->identityBuilder->manufacturedTypeCode($item);
        if ($typeCode !== '' && $typeCode !== 'UNK' && $firstSegment === $typeCode) {
            return true;
        }

        $pcode = strtoupper(trim((string) $item->pcode));
        $normalized = $this->identityBuilder->normalizeManufacturedPcode($pcode);
        if ($firstSegment === $pcode || $firstSegment === $normalized) {
            return true;
        }

        $parentMaster = $this->identityBuilder->manufacturedParentMaster($item);
        if ($firstSegment === $parentMaster) {
            return true;
        }

        return false;
    }
}
