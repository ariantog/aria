<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestockMoveService
{
    private const MOVES = [
        'to_production' => ['from' => 'qty_restock', 'to' => 'qty_production', 'from_field' => 'restock', 'to_field' => 'production'],
        'to_shipped' => ['from' => 'qty_production', 'to' => 'qty_shipped', 'from_field' => 'production', 'to_field' => 'shipped'],
    ];

    /**
     * @param  list<array{id: int, qty?: int}>  $cells
     */
    public function move(RestockSheet $sheet, string $direction, array $cells, User $user): int
    {
        if (! isset(self::MOVES[$direction])) {
            throw new InvalidArgumentException("Unknown move direction: {$direction}");
        }

        if ($cells === []) {
            return 0;
        }

        $move = self::MOVES[$direction];
        $moved = 0;

        DB::transaction(function () use ($sheet, $cells, $user, $move, &$moved) {
            $cellIds = collect($cells)->pluck('id')->all();
            $qtyById = collect($cells)->keyBy('id')->map(fn (array $row) => array_key_exists('qty', $row) ? (int) $row['qty'] : null);

            $models = RestockCell::query()
                ->where('restock_sheet_id', $sheet->id)
                ->whereIn('id', $cellIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($models->count() !== count($cellIds)) {
                throw new InvalidArgumentException('One or more cells do not belong to this sheet.');
            }

            foreach ($cellIds as $cellId) {
                $cell = $models->get($cellId);
                if (! $cell) {
                    continue;
                }

                $available = (int) $cell->{$move['from']};
                $requested = $qtyById->get($cellId);
                $qty = $requested === null ? $available : min(max(0, $requested), $available);

                if ($qty <= 0) {
                    continue;
                }

                $fromBefore = (int) $cell->{$move['from']};
                $toBefore = (int) $cell->{$move['to']};

                $cell->{$move['from']} = $fromBefore - $qty;
                $cell->{$move['to']} = $toBefore + $qty;
                $cell->save();

                RestockCellHistory::create([
                    'restock_cell_id' => $cell->id,
                    'field' => $move['from_field'],
                    'qty_before' => $fromBefore,
                    'qty_after' => $fromBefore - $qty,
                    'action' => 'move',
                    'user_id' => $user->id,
                ]);

                RestockCellHistory::create([
                    'restock_cell_id' => $cell->id,
                    'field' => $move['to_field'],
                    'qty_before' => $toBefore,
                    'qty_after' => $toBefore + $qty,
                    'action' => 'move',
                    'user_id' => $user->id,
                ]);

                $moved += $qty;
            }

            if ($moved > 0) {
                $sheet->update([
                    'last_saved_at' => now(),
                    'last_saved_by' => $user->id,
                ]);
            }
        });

        return $moved;
    }
}
