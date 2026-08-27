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
            $variant = $this->identityBuilder->groupVariant($inputType, $pcode, $warnaTag);
            $storedName = $this->identityBuilder->storedGroupName($inputType, $groupName, $pcode, $variant);
            $storedName = $this->ensureUniqueStoredGroupName(
                $storedName,
                $this->identityBuilder->parsePcode($inputType, $pcode)['master'],
                $variant,
            );
            $nameChanged = strtoupper(trim((string) ($item->group?->name ?? ''))) !== strtoupper($storedName);
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

            if ($nameChanged) {
                $this->syncItemNamesForGroup($group->fresh());
            }

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
            $storedName = $this->identityBuilder->storedGroupName(
                $itemType,
                $productName,
                (string) ($sampleItem?->pcode ?? ''),
                (string) ($group->variant ?? ''),
            );
            $storedName = $this->ensureUniqueStoredGroupName(
                $storedName,
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

        if ($isUpdate && $itemType === ItemType::ITEM) {
            $this->preserveLegacyCode($item, $code);
        }

        $displayName = $productName ?? $this->identityBuilder->productDisplayName(
            $itemType,
            (string) $group->name,
            (string) ($group->variant ?? ''),
        );

        $item->pcode = $pcode;
        $item->code = $code;
        $item->name = $this->identityBuilder->buildName($displayName, $warnaTag, $sizeTag);
        $item->price = $input->price ?? $item->price ?? 0;
        $item->cost = $input->cost ?? $item->cost ?? 0;
        $item->description = $input->description ?? $item->description ?? '';
        $item->description2 = $input->description2 ?? $item->description2 ?? '';
        $item->restock_urgent_threshold = $this->normalizeRestockUrgentThreshold(
            $input->restock_urgent_threshold ?? $item->restock_urgent_threshold
        );
        $item->size = $sizeTag?->id ?? 0;
        $item->genre = $typeTag?->id ?? 0;
        $item->brand = $this->resolveBrand($pcode);
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
        $storedName = $this->identityBuilder->storedGroupName($type, $groupName, $pcode, $variant);
        $storedName = $this->ensureUniqueStoredGroupName($storedName, $parsed['master'], $variant);

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

    protected function ensureUniqueStoredGroupName(string $storedName, string $master, string $variant): string
    {
        $existing = ItemGroup::query()->where('name', $storedName)->first();

        if (! $existing) {
            return $storedName;
        }

        if (strtoupper((string) $existing->master) === strtoupper($master)
            && strtoupper((string) $existing->variant) === strtoupper($variant)) {
            return $storedName;
        }

        return strtoupper(trim("{$storedName} ({$master}/{$variant})"));
    }

    protected function groupNameFromInput(
        object $input,
        ItemType $type,
        string $pcode,
        ?ItemGroup $existing = null,
        ?Item $item = null,
    ): string {
        $name = trim((string) ($input->product_name ?? $input->alias ?? $input->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if ($type === ItemType::ITEM) {
            return $pcode;
        }

        $fallback = trim((string) ($existing?->name ?? ''));

        if ($fallback !== '') {
            return $fallback;
        }

        if ($item !== null) {
            $derived = $this->deriveLegacyAssetProductName($item);

            if ($derived !== '') {
                return $derived;
            }
        }

        throw new Exception('Product name is required.');
    }

    /**
     * Snapshot the pre-identity SKU for Jubelio before code changes on manufactured items.
     * Never overwrites an existing legacy_code.
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

    protected function resolveBrand(string $pcode): ItemBrand
    {
        $brandStr = strtoupper(substr($pcode, 0, 2));
        if ($brandStr === 'CX') {
            $brandStr = strtoupper(substr($pcode, 0, 3));
        }

        foreach (ItemBrand::cases() as $brandCase) {
            if ($brandCase->label() === $brandStr) {
                return $brandCase;
            }
        }

        return ItemBrand::NO_BRAND;
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
