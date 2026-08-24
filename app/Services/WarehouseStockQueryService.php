<?php

namespace App\Services;

use App\Models\Addrbook;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class WarehouseStockQueryService
{
    public const PER_PAGE = 1000;

    public function buildItemsQuery(Addrbook $addrbook, Request $request): BelongsToMany
    {
        $query = $addrbook->items()->with('group')
            ->when($request->input('name'), fn ($q, $v) => $q->where(fn ($sq) => $sq
                ->where('items.name', 'like', '%'.$v.'%')
                ->orWhere('items.code', 'like', '%'.$v.'%')
            ))
            ->when(
                $request->input('show0') !== 'show',
                fn ($q) => $q->where('warehouse_item.quantity', '>=', 1),
            );

        $sort = $request->input('sort', 'qtydesc');
        match ($sort) {
            'qtyasc' => $query->orderByPivot('quantity', 'asc'),
            'codedesc' => $query->orderBy('items.code', 'desc'),
            'codeasc' => $query->orderBy('items.code', 'asc'),
            'namedesc' => $query->orderBy('items.name', 'desc'),
            'nameasc' => $query->orderBy('items.name', 'asc'),
            'iddesc' => $query->orderBy('items.id', 'desc'),
            'idasc' => $query->orderBy('items.id', 'asc'),
            default => $query->orderByPivot('quantity', 'desc'),
        };

        return $query;
    }

    public function resolvePerPage(Request $request): int
    {
        return self::PER_PAGE;
    }
}
