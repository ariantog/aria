<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class ItemListFilter
{
    /**
     * @param  Builder<Item>|Relation<Item, Item, *>  $query
     */
    public function apply(Builder|Relation $query, Request $request): void
    {
        $query->when($request->filled('code'), fn ($q) => $q->filterIndexLookup($request->code))
            ->when($request->filled('name'), fn ($q) => $q->filterDisplayName($request->name))
            ->when($request->filled('desc'), fn ($q) => $q->filterDescription($request->desc));

        $this->applyTagFilters($query, $request);
    }

    /**
     * @return array<int, string>
     */
    public function filterKeys(): array
    {
        return ['code', 'name', 'desc', 'jahit', 'size', 'warna', 'item_type'];
    }

    /**
     * @param  Builder<Item>|Relation<Item, Item, *>  $query
     */
    private function applyTagFilters(Builder|Relation $query, Request $request): void
    {
        $tags = collect([
            $request->input('jahit'),
            $request->input('size'),
            $request->input('warna'),
            $request->input('item_type'),
        ])->flatten()->filter()->toArray();

        $explicit = $request->input('tag_ids', []);
        if (is_array($explicit)) {
            $tags = array_unique(array_merge($tags, $explicit));
        }

        if (! empty($tags)) {
            $query->filterByTags($tags);
        }
    }
}
