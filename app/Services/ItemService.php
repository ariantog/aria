<?php

namespace App\Services;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ItemService
{
    public function __construct(
        protected ImageService $imageService,
        protected InventoryService $inventoryService,
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * @throws Exception
     */
    public function update(int $id, object $input, array $tags, ?UploadedFile $file = null): Item
    {
        $inputType = $this->resolveItemType($input->type ?? ItemType::ITEM->value);

        return DB::transaction(function () use ($id, $input, $tags, $file, $inputType) {
            $item = Item::with(['group', 'tags'])->findOrFail($id);
            $sourceGroupId = (int) $item->group_id;
            $siblings = $sourceGroupId > 0
                ? Item::with('tags')
                    ->where('group_id', $sourceGroupId)
                    ->where('id', '!=', $item->id)
                    ->get()
                : collect();
            $pcode = $this->resolvePcodeForUpdate($item, $inputType, $input);

            try {
                $this->identityBuilder->validatePcode($inputType, $pcode);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            $input->pcode = $pcode;
            $currentTagIds = array_filter(explode(',', (string) $item->tag_ids));

            $tags = $this->sortTags($tags, $inputType);

            if (empty($tags['types'])) {
                $tags['types'] = Tag::whereIn('id', $currentTagIds)
                    ->where('type', Tag::TYPE_TYPE)
                    ->pluck('id')
                    ->toArray();
            }

            if (empty($tags['sizes'])) {
                $tags['sizes'] = Tag::whereIn('id', $currentTagIds)
                    ->where('type', Tag::TYPE_SIZE)
                    ->pluck('id')
                    ->toArray();
            }

            if ($inputType === ItemType::ITEM && empty($tags['warna'])) {
                $tags['warna'] = Tag::whereIn('id', $currentTagIds)
                    ->where('type', Tag::TYPE_WARNA)
                    ->pluck('id')
                    ->toArray();
            }

            $typeId = (int) ($tags['types'][0] ?? 0);
            $sizeId = (int) ($tags['sizes'][0] ?? 0);
            $warnaId = (int) ($tags['warna'][0] ?? 0);

            $typeTag = Tag::find($typeId);
            $sizeTag = Tag::find($sizeId);
            $warnaTag = $warnaId ? Tag::find($warnaId) : null;

            $pcode = strtoupper(trim((string) $input->pcode));
            $groupName = $this->groupNameFromInput($input, $inputType, $pcode, $item->group, $item);
            $group = $this->resolveGroup($inputType, $pcode, $groupName, $warnaTag, $input);

            $this->applyItemIdentity(
                $item,
                $inputType,
                $pcode,
                $group,
                $typeTag,
                $warnaTag,
                $sizeTag,
                $input,
                isUpdate: true,
                productName: $groupName,
            );

            $item->group_id = $group->id;
            $tagIds = $this->collectTagIds($tags, $typeId, $sizeId, $warnaId ?: null);
            $item->tag_ids = implode(',', $tagIds);
            $item->save();
            $item->tags()->sync($tagIds);

            $this->persistGroupCatalogAttributes($group, $item, $input, $typeTag);

            foreach ($siblings as $sibling) {
                $this->applySharedUpdateToSibling(
                    $sibling,
                    $input,
                    $inputType,
                    $pcode,
                    $group,
                    $typeTag,
                    $warnaTag,
                    $tags,
                    $typeId,
                    $warnaId ?: null,
                    $groupName,
                );
            }

            $this->syncItemNamesForGroup($group->fresh());

            if ($file) {
                $this->imageService->saveImage($group, $file);
            }

            return $item->fresh(['group', 'tags']);
        });
    }

    /**
     * Rename the product name on a group and regenerate display names for every item in it.
     */
    public function renameGroupProductName(ItemGroup $group, string $productName): ItemGroup
    {
        $productName = strtoupper(trim($productName));

        if ($productName === '') {
            throw new Exception('Product name is required.');
        }

        return DB::transaction(function () use ($group, $productName) {
            $sampleItem = $group->items()->first();
            $itemType = $sampleItem
                ? $this->resolveItemType($sampleItem->getAttributes()['type'] ?? $sampleItem->type)
                : ItemType::ITEM;
            $productName = $this->identityBuilder->productDisplayName(
                $itemType,
                $productName,
                (string) ($group->variant ?? ''),
                (string) ($group->master ?? ''),
            );
            $storedName = $this->identityBuilder->uniqueStoredGroupName(
                $this->identityBuilder->storedGroupName(
                    $itemType,
                    $productName,
                    (string) ($sampleItem?->pcode ?? ''),
                    (string) ($group->variant ?? ''),
                ),
                (string) ($group->master ?? ''),
                (string) ($group->variant ?? ''),
            );
            $group->name = $storedName;
            $group->save();

            $this->syncItemNamesForGroup($group);

            return $group->fresh();
        });
    }

    /**
     * @throws Exception
     */
    public function create(object $input, array $tags, ?UploadedFile $file = null): bool
    {
        $inputType = $this->resolveItemType($input->type ?? ItemType::ITEM->value);

        try {
            $this->identityBuilder->validatePcode($inputType, (string) ($input->pcode ?? ''));
        } catch (InvalidArgumentException $e) {
            throw new Exception($e->getMessage());
        }

        $tags = $this->sortTags($tags, $inputType);
        $pcode = strtoupper(trim((string) $input->pcode));
        $groupName = $this->groupNameFromInput($input, $inputType, $pcode);

        if ($inputType === ItemType::ITEM && count($tags['warna']) !== 1) {
            throw new Exception('Manufactured items require exactly one WARNA tag.');
        }

        if ($inputType === ItemType::ASSET_LANCAR && empty($tags['warna'])) {
            throw new Exception('Asset lancar requires at least one WARNA tag.');
        }

        if ($inputType === ItemType::ASSET_LANCAR && empty($tags['types'])) {
            throw new Exception('Asset lancar requires a TYPE tag.');
        }

        return DB::transaction(function () use ($input, $tags, $file, $inputType, $groupName, $pcode) {
            $totalCreated = 0;
            $firstItemWithImage = null;
            $warnaIds = $tags['warna'];
            $typeLoops = ! empty($tags['types']) ? $tags['types'] : [0];

            foreach ($typeLoops as $typeId) {
                foreach ($warnaIds as $warnaId) {
                    foreach ($tags['sizes'] as $sizeId) {
                        $typeTag = (int) $typeId > 0 ? Tag::find((int) $typeId) : null;
                        $sizeTag = Tag::find((int) $sizeId);
                        $warnaTag = $warnaId ? Tag::find((int) $warnaId) : null;

                        $group = $this->resolveGroup(
                            $inputType,
                            $pcode,
                            $groupName,
                            $warnaTag,
                            $input,
                        );

                        $item = $this->createSingleItem(
                            $group,
                            $input,
                            $inputType,
                            $typeTag,
                            $warnaTag,
                            $sizeTag,
                            $tags,
                            (int) $typeId,
                            (int) $sizeId,
                            $warnaId ? (int) $warnaId : null,
                            $groupName,
                        );

                        $this->persistGroupCatalogAttributes($group, $item, $input, $typeTag);

                        if ($file) {
                            if (! $firstItemWithImage) {
                                $this->imageService->saveItemImage($item, $file);
                                $firstItemWithImage = $item;
                            } else {
                                $this->imageService->copyItemImage($firstItemWithImage, $item);
                            }
                        }

                        $totalCreated++;
                    }
                }
            }

            if ($totalCreated < 1) {
                throw new Exception('Must have at least one TYPE, SIZE and WARNA tag.');
            }

            return true;
        });
    }

    protected function createSingleItem(
        ItemGroup $group,
        object $input,
        ItemType $itemType,
        ?Tag $typeTag,
        ?Tag $warnaTag,
        ?Tag $sizeTag,
        array $tags,
        int $typeId,
        int $sizeId,
        ?int $warnaId,
        string $groupName,
    ): Item {
        $item = new Item;
        $item->type = $itemType;
        $item->group_id = $group->id;

        $this->applyItemIdentity(
            $item,
            $itemType,
            (string) $input->pcode,
            $group,
            $typeTag,
            $warnaTag,
            $sizeTag,
            $input,
            productName: $groupName,
        );

        $tagIds = $this->collectTagIds($tags, $typeId, $sizeId, $warnaId);
        $item->tag_ids = implode(',', $tagIds);
        $item->save();
        $item->tags()->sync($tagIds);

        return $item;
    }

    protected function applyItemIdentity(
        Item $item,
        ItemType $itemType,
        string $pcode,
        ItemGroup $group,
        ?Tag $typeTag,
        ?Tag $warnaTag,
        ?Tag $sizeTag,
        object $input,
        bool $isUpdate = false,
        ?string $productName = null,
    ): void {
        $pcode = strtoupper(trim($pcode));
        $code = $this->identityBuilder->buildCode($itemType, $pcode, $typeTag, $warnaTag, $sizeTag);

        if (Item::query()->whereSku($code)->where('id', '!=', $item->id ?? 0)->exists()) {
            throw new Exception("SKU already exists: {$code}");
        }

        if ($isUpdate) {
            $this->preserveLegacyCode($item, $code);
        }

        $displayName = $this->identityBuilder->productDisplayName(
            $itemType,
            $productName ?? (string) $group->name,
            (string) ($group->variant ?? ''),
            (string) ($group->master ?? ''),
        );

        $item->pcode = $pcode;
        $item->code = $code;
        $item->name = $this->identityBuilder->buildName($displayName, $warnaTag, $sizeTag);
        $item->price = $input->price ?? $item->price ?? 0;
        $item->cost = $input->cost ?? $item->cost ?? 0;
        // Group description is canonical for display. Mirror onto the item so
        // the leftover items.description column stays aligned after an edit.
        $item->description = $input->description ?? $item->description ?? '';
        $item->description2 = $input->description2 ?? $item->description2 ?? '';
        $item->restock_urgent_threshold = $this->normalizeRestockUrgentThreshold(
            $input->restock_urgent_threshold ?? $item->restock_urgent_threshold
        );
        $item->size = $sizeTag?->id ?? 0;
        $item->genre = $typeTag?->id ?? 0;
        $item->brand = ItemBrand::fromPcode($pcode);
    }

    protected function resolveGroup(
        ItemType $type,
        string $pcode,
        string $groupName,
        ?Tag $warnaTag,
        object $input,
    ): ItemGroup {
        $parsed = $this->identityBuilder->parsePcode($type, $pcode);
        $variant = $this->identityBuilder->groupVariant($type, $pcode, $warnaTag);
        $storedName = $this->identityBuilder->uniqueStoredGroupName(
            $this->identityBuilder->storedGroupName($type, $groupName, $pcode, $variant),
            $parsed['master'],
            $variant,
        );

        $group = ItemGroup::firstOrCreate(
            [
                'master' => $parsed['master'],
                'variant' => $variant,
            ],
            [
                'name' => $storedName,
                'description' => isset($input->description) ? strtoupper($input->description) : null,
                'description2' => isset($input->description2) ? strtoupper($input->description2) : null,
                'url' => $this->normalizeUrl($input->url ?? null),
            ],
        );

        if (strtoupper(trim((string) $group->name)) !== strtoupper($storedName)) {
            $group->name = $storedName;
        }

        if (isset($input->description)) {
            $group->description = strtoupper($input->description);
        }

        if (isset($input->description2)) {
            $group->description2 = strtoupper($input->description2);
        }

        if (isset($input->url)) {
            $group->url = $this->normalizeUrl($input->url);
        }

        $group->save();

        return $group;
    }

    protected function groupNameFromInput(
        object $input,
        ItemType $type,
        string $pcode,
        ?ItemGroup $existing = null,
        ?Item $item = null,
    ): string {
        $name = trim((string) ($input->product_name ?? $input->alias ?? $input->name ?? ''));

        if ($name !== '' && ! $this->isPcodeLikeProductName($name, $pcode, $item)) {
            return $this->identityBuilder->productDisplayName(
                $type,
                $name,
                (string) ($existing?->variant ?? ''),
                (string) ($existing?->master ?? ''),
            );
        }

        // Create: inherit a custom title already stored for this pcode.
        // Update: an empty / pcode-like name means group.name tracks pcode.
        if ($item === null) {
            $fromPcode = $this->productNameForPcode($type, $pcode);

            if ($fromPcode !== null && ! $this->isPcodeLikeProductName($fromPcode, $pcode, null)) {
                return $fromPcode;
            }
        }

        if ($type === ItemType::ITEM) {
            return $pcode;
        }

        $fallback = trim((string) ($existing?->name ?? ''));

        if ($fallback !== '') {
            return $this->identityBuilder->productDisplayName(
                $type,
                $fallback,
                (string) ($existing?->variant ?? ''),
                (string) ($existing?->master ?? ''),
            );
        }

        if ($item !== null) {
            $derived = $this->deriveLegacyAssetProductName($item);

            if ($derived !== '') {
                return $this->identityBuilder->productDisplayName($type, $derived, '', '');
            }
        }

        throw new Exception('Product name is required.');
    }

    /**
     * Bare product title already stored for this pcode, if any.
     */
    public function productNameForPcode(ItemType $type, string $pcode): ?string
    {
        $pcode = strtoupper(trim($pcode));

        if ($pcode === '') {
            return null;
        }

        $item = Item::query()
            ->where('type', $type)
            ->whereRaw('UPPER(TRIM(pcode)) = ?', [$pcode])
            ->whereNull('deleted_at')
            ->with('group')
            ->orderByDesc('id')
            ->first();

        if (! $item) {
            return null;
        }

        if ($item->group) {
            $fromGroup = $this->identityBuilder->productDisplayName(
                $type,
                (string) $item->group->name,
                (string) ($item->group->variant ?? ''),
                (string) ($item->group->master ?? ''),
            );

            if ($fromGroup !== '' && $fromGroup !== $pcode) {
                return $fromGroup;
            }
        }

        $fromItem = $type === ItemType::ASSET_LANCAR
            ? $this->deriveLegacyAssetProductName($item)
            : strtoupper(trim(explode(' - ', (string) $item->name, 2)[0]));

        if ($fromItem === '' || $fromItem === $pcode) {
            return null;
        }

        return $this->identityBuilder->productDisplayName($type, $fromItem, '', '');
    }

    /**
     * Snapshot the previous SKU for Jubelio before code changes on edit
     * (manufactured items and asset lancar). Never overwrites an existing legacy_code.
     */
    protected function preserveLegacyCode(Item $item, string $newCode): void
    {
        if (trim((string) ($item->legacy_code ?? '')) !== '') {
            return;
        }

        $currentCode = strtoupper(trim((string) ($item->code ?? '')));
        $newCode = strtoupper(trim($newCode));

        if ($currentCode !== '' && $currentCode !== $newCode) {
            $item->legacy_code = $currentCode;
        }
    }

    /**
     * Derive pcode for legacy rows on update (e.g. asset lancar SKU not yet normalized).
     */
    protected function resolvePcodeForUpdate(Item $item, ItemType $type, object $input): string
    {
        $pcode = strtoupper(trim((string) ($input->pcode ?? '')));

        if ($pcode !== '') {
            try {
                $this->identityBuilder->validatePcode($type, $pcode);

                return $pcode;
            } catch (InvalidArgumentException) {
                // Fall through for legacy asset lancar rows.
            }
        }

        if ($type === ItemType::ASSET_LANCAR) {
            $derived = $this->identityBuilder->assetLancarParentPcode($item);
            $this->identityBuilder->validatePcode($type, $derived);

            return $derived;
        }

        if ($pcode !== '') {
            throw new Exception('pcode format invalid.');
        }

        throw new Exception('pcode is required');
    }

    protected function deriveLegacyAssetProductName(Item $item): string
    {
        $name = trim((string) $item->name);

        if ($name === '') {
            return $this->identityBuilder->assetLancarParentPcode($item);
        }

        if (str_contains($name, ' - ')) {
            return trim(explode(' - ', $name, 2)[0]);
        }

        return $name;
    }

    protected function syncItemNamesForGroup(ItemGroup $group): void
    {
        $items = Item::with('tags')->where('group_id', $group->id)->get();
        $sampleType = $this->resolveItemType($items->first()?->type ?? ItemType::ITEM->value);
        $displayName = $this->identityBuilder->productDisplayName(
            $sampleType,
            (string) $group->name,
            (string) ($group->variant ?? ''),
            (string) ($group->master ?? ''),
        );

        foreach ($items as $item) {
            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
            $item->name = $this->identityBuilder->buildName($displayName, $warnaTag, $sizeTag);
            $item->save();
        }
    }

    public function isPlaceholderProductName(ItemType $type, string $groupName, string $pcode): bool
    {
        if ($type !== ItemType::ITEM) {
            return false;
        }

        return strtoupper(trim($groupName)) === strtoupper(trim($pcode));
    }

    protected function collectTagIds(array $tags, int $typeId, int $sizeId, ?int $warnaId): array
    {
        $jahitTags = $tags['jahit'] ?? [];
        if (! is_array($jahitTags)) {
            $jahitTags = $jahitTags ? [$jahitTags] : [];
        }

        $tagIds = array_merge(
            $jahitTags,
            array_filter([$typeId, $sizeId, $warnaId]),
        );

        $tagIds = array_unique(array_filter($tagIds));
        sort($tagIds);

        return $tagIds;
    }

    protected function applySharedUpdateToSibling(
        Item $sibling,
        object $input,
        ItemType $itemType,
        string $pcode,
        ItemGroup $group,
        ?Tag $typeTag,
        ?Tag $warnaTag,
        array $tags,
        int $typeId,
        ?int $warnaId,
        string $groupName,
    ): void {
        $sizeTag = $sibling->tags->firstWhere('type', Tag::TYPE_SIZE);
        if (! $sizeTag && (int) $sibling->size > 0) {
            $sizeTag = Tag::find((int) $sibling->size);
        }

        $siblingInput = clone $input;
        $siblingInput->cost = $sibling->cost;
        $siblingInput->restock_urgent_threshold = $sibling->restock_urgent_threshold;

        $sibling->group_id = $group->id;

        $this->applyItemIdentity(
            $sibling,
            $itemType,
            $pcode,
            $group,
            $typeTag,
            $warnaTag,
            $sizeTag,
            $siblingInput,
            isUpdate: true,
            productName: $groupName,
        );

        $sizeId = (int) ($sizeTag?->id ?? 0);
        $tagIds = $this->collectTagIds($tags, $typeId, $sizeId, $warnaId);
        $sibling->tag_ids = implode(',', $tagIds);
        $sibling->save();
        $sibling->tags()->sync($tagIds);
    }

    protected function persistGroupCatalogAttributes(ItemGroup $group, Item $item, object $input, ?Tag $typeTag): void
    {
        if (isset($input->description)) {
            $group->description = strtoupper((string) $input->description);
        }

        if (isset($input->description2)) {
            $group->description2 = strtoupper((string) $input->description2);
        }

        if (isset($input->url)) {
            $group->url = $this->normalizeUrl($input->url);
        }

        $brand = $item->brand instanceof ItemBrand
            ? $item->brand
            : ItemBrand::fromPcode((string) $item->pcode);

        if (Schema::hasColumn($group->getTable(), 'brand')) {
            $group->brand = $brand;
        }

        if (Schema::hasColumn($group->getTable(), 'genre')) {
            $group->genre = (int) ($typeTag?->id ?? $item->genre ?? 0);
        }

        $group->save();
    }

    /**
     * Group name that is only the shared pcode (slash or hyphen form).
     */
    protected function isPcodeLikeProductName(string $name, string $pcode, ?Item $item): bool
    {
        $normalize = static fn (string $value): string => strtoupper(str_replace(['/', ' '], ['-', ''], trim($value)));
        $nameNorm = $normalize($name);

        foreach (array_filter([$pcode, (string) ($item?->pcode ?? '')]) as $candidate) {
            if ($nameNorm === $normalize((string) $candidate)) {
                return true;
            }
        }

        return $this->isPlaceholderProductName(
            $item?->type instanceof ItemType ? $item->type : ItemType::ITEM,
            $name,
            $pcode,
        );
    }

    protected function resolveBrand(string $pcode): ItemBrand
    {
        return ItemBrand::fromPcode($pcode);
    }

    protected function sortTags(array $tags, ItemType $type): array
    {
        $warna = $tags['warna'] ?? [];
        if (! is_array($warna)) {
            $warna = $warna !== null && $warna !== '' ? [$warna] : [];
        }

        $types = $tags['types'] ?? [];
        if (! is_array($types)) {
            $types = [$types];
        }

        $sizes = $tags['sizes'] ?? [];
        if (! is_array($sizes)) {
            $sizes = [$sizes];
        }

        if ($sizes === []) {
            $sizes = [0];
        }

        $jahit = $tags['jahit'] ?? [];
        if (! is_array($jahit)) {
            $jahit = $jahit ? [$jahit] : [];
        }

        return [
            'warna' => array_values(array_filter($warna)),
            'types' => array_values(array_filter($types)),
            'sizes' => array_values(array_filter($sizes)),
            'jahit' => array_values(array_filter($jahit)),
        ];
    }

    private function resolveItemType(mixed $value): ItemType
    {
        if ($value instanceof ItemType) {
            return $value;
        }

        return ItemType::tryFrom((int) $value) ?? ItemType::ITEM;
    }

    private function normalizeUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));

        return $url === '' ? null : $url;
    }

    private function normalizeRestockUrgentThreshold(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $threshold = (int) $value;

        return $threshold > 0 ? $threshold : null;
    }
}
