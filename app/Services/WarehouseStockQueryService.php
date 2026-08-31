<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Services\ItemListFilter;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class WarehouseStockQueryService
{
    public const PER_PAGE = 1000;

    public function __construct(
        protected ItemListFilter $itemListFilter,
    ) {}

    public function buildItemsQuery(Addrbook $addrbook, Request $request): BelongsToMany
    {
        $query = $addrbook->items()->with('group');

        $this->itemListFilter->apply($query, $request);

        $query->when(
            $request->input('show0') !== 'show',
            fn ($q) => $q->where('warehouse_item.quantity', '>=', 1),
        );

        $sort = $request->input('sort', 'codeasc');
        match ($sort) {
            'qtyasc' => $query->orderByPivot('quantity', 'asc'),
            'qtydesc' => $query->orderByPivot('quantity', 'desc'),
            'codedesc' => $query->orderBy('items.code', 'desc'),
            'codeasc' => $query->orderBy('items.code', 'asc'),
            'namedesc' => $query->orderBy('items.name', 'desc'),
            'nameasc' => $query->orderBy('items.name', 'asc'),
            'pricedesc' => $query->orderBy('items.price', 'desc'),
            'priceasc' => $query->orderBy('items.price', 'asc'),
            'iddesc' => $query->orderBy('items.id', 'desc'),
            'idasc' => $query->orderBy('items.id', 'asc'),
            default => $query->orderBy('items.code', 'asc'),
        };

        return $query;
    }

    public function resolvePerPage(Request $request): int
    {
        return self::PER_PAGE;
    }
}
