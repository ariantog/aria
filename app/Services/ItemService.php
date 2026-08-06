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
        $inputType = ItemType::tryFrom($input->type ?? 1) ?? ItemType::ITEM;

        try {
            $this->identityBuilder->validatePcode($inputType, (string) ($input->pcode ?? ''));
        } catch (InvalidArgumentException $e) {
            throw new Exception($e->getMessage());
        }

        return DB::transaction(function () use ($id, $input, $tags, $file, $inputType) {
            $item = Item::with('group')->findOrFail($id);
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
            $previousGroupName = $item->group?->name ?? '';
            $groupName = $this->groupNameFromInput($input, $inputType, $pcode, $item->group);
            $nameChanged = strtoupper($previousGroupName) !== strtoupper($groupName);
            $group = $this->resolveGroup($inputType, $pcode, $groupName, $warnaTag, $input);

            $this->applyItemIdentity(
                $item,
                $inputType,
                (string) $input->pcode,
                $group,
                $typeTag,
                $warnaTag,
                $sizeTag,
                $input,
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
            $group->name = $productName;
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
        $inputType = ItemType::tryFrom($input->type ?? 1) ?? ItemType::ITEM;

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
    ): void {
        $pcode = strtoupper(trim($pcode));
        $code = $this->identityBuilder->buildCode($itemType, $pcode, $typeTag, $warnaTag, $sizeTag);

        if (Item::query()->whereSku($code)->where('id', '!=', $item->id ?? 0)->exists()) {
            throw new Exception("SKU already exists: {$code}");
        }

        $item->pcode = $pcode;
        $item->code = $code;
        $item->name = $this->identityBuilder->buildName($group->name, $warnaTag, $sizeTag);
        $item->price = $input->price ?? $item->price ?? 0;
        $item->cost = $input->cost ?? $item->cost ?? 0;
        $item->description = $input->description ?? $item->description ?? '';
        $item->description2 = $input->description2 ?? $item->description2 ?? '';
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

        $group = ItemGroup::firstOrCreate(
            [
                'master' => $parsed['master'],
                'variant' => $variant,
            ],
            [
                'name' => strtoupper($groupName),
                'description' => isset($input->description) ? strtoupper($input->description) : null,
                'description2' => isset($input->description2) ? strtoupper($input->description2) : null,
            ],
        );

        $group->name = strtoupper($groupName);

        if (isset($input->description)) {
            $group->description = strtoupper($input->description);
        }

        if (isset($input->description2)) {
            $group->description2 = strtoupper($input->description2);
        }

        $group->save();

        return $group;
    }

    protected function groupNameFromInput(
        object $input,
        ItemType $type,
        string $pcode,
        ?ItemGroup $existing = null,
    ): string {
        $name = trim((string) ($input->alias ?? $input->name ?? ''));

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

        throw new Exception('Product name is required.');
    }

    protected function syncItemNamesForGroup(ItemGroup $group): void
    {
        $items = Item::with('tags')->where('group_id', $group->id)->get();

        foreach ($items as $item) {
            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
            $item->name = $this->identityBuilder->buildName($group->name, $warnaTag, $sizeTag);
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
}
