<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\User;
use App\Support\RestockCellHistoryLabels;

class RestockCellHistoryRecorder
{
    public function record(
        RestockCell $cell,
        string $field,
        int $qtyBefore,
        int $qtyAfter,
        string $action,
        ?User $user = null,
        ?string $source = null,
        ?string $actorType = null,
        ?int $transactionId = null,
        ?string $note = null,
    ): RestockCellHistory {
        if ($qtyBefore === $qtyAfter && $action !== 'sync_remove') {
            throw new \InvalidArgumentException('History row requires a quantity change.');
        }

        $resolvedActor = $actorType ?? ($user !== null
            ? RestockCellHistoryLabels::ACTOR_USER
            : RestockCellHistoryLabels::ACTOR_SYSTEM);

        return RestockCellHistory::create([
            'restock_cell_id' => $cell->id,
            'restock_sheet_id' => $cell->restock_sheet_id,
            'item_id' => $cell->item_id,
            'field' => $field,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'action' => $action,
            'source' => $source,
            'actor_type' => $resolvedActor,
            'user_id' => $user?->id,
            'transaction_id' => $transactionId,
            'note' => $note,
        ]);
    }

    /**
     * @param  list<string>  $fields
     */
    public function recordCellRemovalSnapshot(
        RestockCell $cell,
        array $fields,
        ?User $user = null,
        ?string $note = null,
    ): void {
        foreach ($fields as $field) {
            $column = match ($field) {
                'restock' => 'qty_restock',
                'production' => 'qty_production',
                'shipped' => 'qty_shipped',
                'missing' => 'qty_missing',
                default => null,
            };

            if ($column === null) {
                continue;
            }

            $before = (int) $cell->{$column};
            if ($before === 0) {
                continue;
            }

            $this->record(
                $cell,
                $field,
                $before,
                0,
                'sync_remove',
                $user,
                RestockCellHistoryLabels::SOURCE_SYNC_SKUS,
                RestockCellHistoryLabels::ACTOR_USER,
                null,
                $note,
            );
        }
    }
}
