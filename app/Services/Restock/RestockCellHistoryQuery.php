<?php

namespace App\Services\Restock;

use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RestockCellHistoryQuery
{
    /**
     * @param  array{
     *     sheet_id?: int|null,
     *     item_id?: int|null,
     *     actor_type?: string|null,
     *     source?: string|null,
     *     action?: string|null,
     *     user_id?: int|null,
     *     from?: string|null,
     *     to?: string|null,
     * }  $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters): Builder
    {
        $query = RestockCellHistory::query()
            ->with([
                'user:id,username,name',
                'cell.item:id,code,name',
                'sheet:id,name,type_tag_id',
                'sheet.typeTag:id,code,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['sheet_id'])) {
            $query->where('restock_sheet_id', (int) $filters['sheet_id']);
        }

        if (! empty($filters['item_id'])) {
            $query->where('item_id', (int) $filters['item_id']);
        }

        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', (string) $filters['actor_type']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', (string) $filters['source']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', (string) $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    public function sheetOptions(): \Illuminate\Support\Collection
    {
        return RestockSheet::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
