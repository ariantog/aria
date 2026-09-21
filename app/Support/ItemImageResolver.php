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
        return $this->resolveExistingDiskPathForId($id) !== null;
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
        $existing = $this->resolveExistingDiskPathForItem($item);

        if ($existing !== null) {
            return $existing;
        }

        return $this->diskPathForId((int) (($item->group_id > 0) ? $item->group_id : $item->id));
    }

    public function resolveExistingDiskPathForItem(Item $item): ?string
    {
        foreach ($this->uniqueCandidateIdsForItem($item) as $id) {
            $path = $this->resolveExistingDiskPathForId($id);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    public function resolveExistingDiskPathForId(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        foreach ($this->diskPathCandidatesForId($id) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Map a browser-facing image URL (relative or absolute CDN) to a readable local file.
     */
    public function resolveExistingDiskPathFromImageUrl(string $url): ?string
    {
        if ($url === '' || str_contains($url, 'default-item.svg')) {
            return null;
        }

        $pathPart = parse_url($url, PHP_URL_PATH);
        if (! is_string($pathPart) || $pathPart === '') {
            $pathPart = str_starts_with($url, '/') ? $url : null;
        }

        if ($pathPart === null) {
            return null;
        }

        if (preg_match('#/img/items/\d+/(\d+)\.(jpe?g|png|gif)$#i', $pathPart, $matches) === 1) {
            $fromId = $this->resolveExistingDiskPathForId((int) $matches[1]);
            if ($fromId !== null) {
                return $fromId;
            }
        }

        if (preg_match('#/(?:asset|img/items)/(\d{2})/(\d+)\.(jpe?g|png|gif)$#i', $pathPart, $matches) === 1) {
            $fromId = $this->resolveExistingDiskPathForId((int) $matches[2]);
            if ($fromId !== null) {
                return $fromId;
            }
        }

        foreach ($this->urlPathPrefixes() as $prefix) {
            if (! str_starts_with($pathPart, $prefix)) {
                continue;
            }

            $relative = substr($pathPart, strlen($prefix));
            foreach ($this->imageStorageBases() as $base) {
                $diskPath = rtrim($base, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_file($diskPath)) {
                    return $diskPath;
                }
            }
        }

        return $this->mapConfiguredUrlToDiskPath($url);
    }

    /**
     * @return list<string> Absolute URLs to try when downloading an image for export.
     */
    public function absoluteImageUrlsForFetch(string $url): array
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return [$url];
        }

        if (str_starts_with($url, '//')) {
            return ['https:'.$url];
        }

        $path = str_starts_with($url, '/') ? $url : '/'.ltrim($url, '/');
        $candidates = [];

        foreach ($this->configuredAbsoluteUrlOrigins() as $origin) {
            $candidates[] = $origin.$path;
        }

        $candidates[] = rtrim((string) config('app.url'), '/').$path;

        return array_values(array_unique($candidates));
    }

    public function mapConfiguredUrlToDiskPath(string $imageUrl): ?string
    {
        $pairs = [
            [config('core-nation.item_image_url'), config('core-nation.item_image_path')],
            [config('core-nation.cdn_url'), config('core-nation.cdn_path')],
        ];

        foreach ($pairs as [$urlBase, $pathBase]) {
            if (! is_string($urlBase) || ! is_string($pathBase)) {
                continue;
            }

            $normalizedUrl = rtrim($urlBase, '/').'/';
            if ($normalizedUrl === '/' || ! str_starts_with($imageUrl, $normalizedUrl)) {
                continue;
            }

            $relative = substr($imageUrl, strlen($normalizedUrl));
            $diskPath = rtrim($pathBase, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($diskPath)) {
                return $diskPath;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function urlPathPrefixes(): array
    {
        $prefixes = ['/img/items/', '/asset/'];

        foreach ([config('core-nation.item_image_url'), config('core-nation.cdn_url')] as $base) {
            if (! is_string($base)) {
                continue;
            }

            $path = parse_url($base, PHP_URL_PATH);
            if (is_string($path) && $path !== '' && $path !== '/') {
                $prefixes[] = rtrim($path, '/').'/';
            }
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * @return list<string>
     */
    protected function configuredAbsoluteUrlOrigins(): array
    {
        $origins = [];

        foreach ([config('core-nation.cdn_url'), config('core-nation.item_image_url')] as $base) {
            if (! is_string($base) || ! str_contains($base, '://')) {
                continue;
            }

            $parsed = parse_url($base);
            if (! isset($parsed['scheme'], $parsed['host'])) {
                continue;
            }

            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            $origins[] = $parsed['scheme'].'://'.$parsed['host'].$port;
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    public function diskPathCandidatesForId(int $id): array
    {
        $folder = $this->folderForId($id);
        $filename = $this->filenameForId($id);
        $paths = [];

        foreach ($this->imageStorageBases() as $base) {
            $paths[] = rtrim($base, '/\\').DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$filename;
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    protected function imageStorageBases(): array
    {
        $bases = [
            config('core-nation.item_image_path'),
            config('core-nation.cdn_path'),
        ];

        return array_values(array_unique(array_filter(array_map(
            static fn ($base) => is_string($base) ? rtrim($base, '/\\') : null,
            $bases,
        ))));
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
