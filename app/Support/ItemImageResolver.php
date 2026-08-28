<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemGroup;
use Illuminate\Support\Collection;

class ItemImageResolver
{
    public function defaultImageUrl(): string
    {
        return asset('images/default-item.svg');
    }

    public function folderForId(int $id): string
    {
        return str_pad(substr((string) $id, -2), 2, '0', STR_PAD_LEFT);
    }

    public function filenameForId(int $id): string
    {
        return $id.'.jpg';
    }

    public function diskPathForId(int $id): string
    {
        $folder = $this->folderForId($id);

        return config('core-nation.item_image_path').$folder.'/'.$this->filenameForId($id);
    }

    public function publicUrlForId(int $id): string
    {
        $folder = $this->folderForId($id);

        return config('core-nation.item_image_url').$folder.'/'.$this->filenameForId($id);
    }

    public function existsForId(int $id): bool
    {
        return file_exists($this->diskPathForId($id));
    }

    public function resolveUrlForId(int $id): ?string
    {
        return $this->existsForId($id) ? $this->publicUrlForId($id) : null;
    }

    /**
     * Resolve image URL for a SKU (type-code-color when grouped, else item id).
     *
     * Fallback order:
     * 1. Current item_group id (canonical type-code-color image)
     * 2. Item id (legacy asset lancar per-SKU uploads)
     * 3. Any sibling SKU in the same group
     */
    public function resolveUrlForItem(Item $item): string
    {
        $candidates = [];

        if ((int) $item->group_id > 0) {
            $candidates[] = (int) $item->group_id;
        }

        $candidates[] = (int) $item->id;

        if ($item->relationLoaded('group') && $item->group?->relationLoaded('items')) {
            foreach ($item->group->items as $sibling) {
                $candidates[] = (int) $sibling->id;
            }
        }

        return $this->resolveUrlFromCandidateIds($candidates);
    }

    /**
     * Resolve image URL for an item_group row (one color / type-code-color variant).
     *
     * Fallback order:
     * 1. Group id
     * 2. Any item in the group (legacy per-SKU uploads)
     */
    public function resolveUrlForGroup(ItemGroup $group, ?Collection $items = null): string
    {
        $candidates = [(int) $group->id];

        $items ??= $group->relationLoaded('items')
            ? $group->items
            : $group->items()->pluck('id');

        foreach ($items as $item) {
            $candidates[] = (int) (is_object($item) ? $item->id : $item);
        }

        return $this->resolveUrlFromCandidateIds($candidates);
    }

    /**
     * Parent group list/detail: first image found on any color variant or child SKU.
     *
     * @param  Collection<int, ItemGroup>|array<int, ItemGroup>  $groups
     */
    public function resolveUrlForGroups(Collection|array $groups): string
    {
        $candidates = [];

        foreach ($groups as $group) {
            $candidates[] = (int) $group->id;

            if ($group->relationLoaded('items')) {
                foreach ($group->items as $item) {
                    $candidates[] = (int) $item->id;
                }
            }
        }

        return $this->resolveUrlFromCandidateIds($candidates);
    }

    public function resolveDiskPathForItem(Item $item): string
    {
        $url = $this->resolveUrlForItem($item);

        if ($url === $this->defaultImageUrl()) {
            return $this->diskPathForId((int) (($item->group_id > 0) ? $item->group_id : $item->id));
        }

        foreach ($this->uniqueCandidateIdsForItem($item) as $id) {
            $path = $this->diskPathForId($id);

            if (file_exists($path)) {
                return $path;
            }
        }

        return $this->diskPathForId((int) (($item->group_id > 0) ? $item->group_id : $item->id));
    }

    /**
     * @param  list<int>  $candidateIds
     */
    public function resolveUrlFromCandidateIds(array $candidateIds): string
    {
        foreach ($this->uniqueIds($candidateIds) as $id) {
            $url = $this->resolveUrlForId($id);

            if ($url !== null) {
                return $url;
            }
        }

        return $this->defaultImageUrl();
    }

    /**
     * @return list<int>
     */
    protected function uniqueCandidateIdsForItem(Item $item): array
    {
        $candidates = [];

        if ((int) $item->group_id > 0) {
            $candidates[] = (int) $item->group_id;
        }

        $candidates[] = (int) $item->id;

        if ($item->relationLoaded('group') && $item->group?->relationLoaded('items')) {
            foreach ($item->group->items as $sibling) {
                $candidates[] = (int) $sibling->id;
            }
        }

        return $this->uniqueIds($candidates);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    protected function uniqueIds(array $ids): array
    {
        $seen = [];
        $unique = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $unique[] = $id;
        }

        return $unique;
    }
}
