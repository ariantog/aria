<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestockCellService
{
  /**
   * @param  list<array{id: int, qty_restock?: int, qty_production?: int, qty_shipped?: int, qty_missing?: int}>  $cells
   */
  public function saveQuantities(RestockSheet $sheet, array $cells, User $user): int
  {
    if ($cells === []) {
      return 0;
    }

    $changes = 0;

    DB::transaction(function () use ($sheet, $cells, $user, &$changes) {
      $cellIds = collect($cells)->pluck('id')->all();
      $models = RestockCell::query()
        ->where('restock_sheet_id', $sheet->id)
        ->whereIn('id', $cellIds)
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

      if ($models->count() !== count($cellIds)) {
        throw new InvalidArgumentException('One or more cells do not belong to this sheet.');
      }

      foreach ($cells as $payload) {
        $cell = $models->get($payload['id']);
        if (! $cell) {
          continue;
        }

        foreach ([
          'qty_restock' => 'restock',
          'qty_production' => 'production',
          'qty_shipped' => 'shipped',
          'qty_missing' => 'missing',
        ] as $column => $field) {
          if (! array_key_exists($column, $payload)) {
            continue;
          }

          $before = (int) $cell->{$column};
          $after = max(0, (int) $payload[$column]);

          if ($before === $after) {
            continue;
          }

          $cell->{$column} = $after;
          $changes++;

          RestockCellHistory::create([
            'restock_cell_id' => $cell->id,
            'field' => $field,
            'qty_before' => $before,
            'qty_after' => $after,
            'action' => 'edit',
            'user_id' => $user->id,
          ]);
        }

        $cell->save();
      }

      $sheet->update([
        'last_saved_at' => now(),
        'last_saved_by' => $user->id,
      ]);
    });

    return $changes;
  }
}
