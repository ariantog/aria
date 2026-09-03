<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestockMissingService
{
    /**
     * @return Collection<int, array{
     *   cell_id: int,
     *   sheet_id: int,
     *   type_name: string,
     *   type_code: string,
     *   sku_code: string,
     *   sku_legacy_code: ?string,
     *   sku_name: string,
     *   qty_missing: int,
     *   missing_at: ?string,
     * }>
     */
    public function listForType(?Tag $typeTag = null): Collection
    {
        return RestockCell::query()
            ->where('qty_missing', '>', 0)
            ->with(['item', 'sheet.typeTag', 'color', 'size'])
            ->when($typeTag, fn ($q) => $q->whereHas('sheet', fn ($sq) => $sq->where('type_tag_id', $typeTag->id)))
            ->orderByDesc('missing_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RestockCell $cell) => [
                'cell_id' => $cell->id,
                'sheet_id' => $cell->restock_sheet_id,
                'type_name' => $cell->sheet?->typeTag?->name ?? '—',
                'type_code' => $cell->sheet?->typeTag?->code ?? '—',
                'sku_code' => $cell->item?->code ?? '—',
                'sku_legacy_code' => $cell->item?->distinctLegacyCode(),
                'sku_name' => $cell->item?->name ?? '—',
                'qty_missing' => (int) $cell->qty_missing,
                'missing_at' => $cell->missing_at?->toDateString(),
            ]);
    }

    public function missingCountForType(?Tag $typeTag = null): int
    {
        return RestockCell::query()
            ->where('qty_missing', '>', 0)
            ->when($typeTag, fn ($q) => $q->whereHas('sheet', fn ($sq) => $sq->where('type_tag_id', $typeTag->id)))
            ->count();
    }

    public function markFound(RestockCell $cell, User $user): void
    {
        if ((int) $cell->qty_missing <= 0) {
            throw new InvalidArgumentException('This SKU has no missing quantity.');
        }

        DB::transaction(function () use ($cell, $user) {
            $locked = RestockCell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();

            $before = (int) $locked->qty_missing;
            if ($before <= 0) {
                throw new InvalidArgumentException('This SKU has no missing quantity.');
            }

            $locked->qty_missing = 0;
            $locked->missing_at = null;
            $locked->save();

            RestockCellHistory::create([
                'restock_cell_id' => $locked->id,
                'field' => 'missing',
                'qty_before' => $before,
                'qty_after' => 0,
                'action' => 'found',
                'user_id' => $user->id,
            ]);

            $locked->sheet?->update([
                'last_saved_at' => now(),
                'last_saved_by' => $user->id,
            ]);
        });
    }
}
