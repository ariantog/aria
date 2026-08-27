<?php

namespace App\Services\Jubelio;

use App\Models\Jubeliosync;
use App\Models\Transaction;

class JubelioTransactionSyncPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Transaction $transaction, ?array $syncedWarehouseIds = null): array
    {
        $transaction->loadMissing(['sender', 'receiver', 'submitByA', 'submitByB', 'details.item']);

        $canSync = $transaction->isAriaSubmit();
        $type = (int) $transaction->type;
        $senderId = (int) $transaction->sender_id;
        $receiverId = (int) $transaction->receiver_id;

        $syncRelevantA = in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER, Transaction::TYPE_MOVE], true);
        $syncRelevantB = in_array($type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN, Transaction::TYPE_MOVE], true);
        $syncedWarehouseIds = array_map(
            'intval',
            $syncedWarehouseIds ?? $this->syncedWarehouseIds()
        );

        $jubSyncA = $this->findSyncForWarehouse($senderId);
        $jubSyncB = $this->findSyncForWarehouse($receiverId);
        $senderSynced = $syncRelevantA && $this->isSyncedWarehouse($senderId, $syncedWarehouseIds);
        $receiverSynced = $syncRelevantB && $this->isSyncedWarehouse($receiverId, $syncedWarehouseIds);

        $jubelioA = ($canSync && $senderSynced && $jubSyncA) ? $jubSyncA->jubelio_location_name : null;
        $jubelioB = ($canSync && $receiverSynced && $jubSyncB) ? $jubSyncB->jubelio_location_name : null;
        $syncCek = $canSync ? $this->resolveSyncCek($type, $senderSynced, $receiverSynced) : null;

        $adjustTypeA = ($senderSynced && $jubSyncA) ? 2 : 0;
        $adjustTypeB = ($receiverSynced && $jubSyncB) ? 1 : 0;

        $mappingMissing = $transaction->details->filter(
            fn ($detail) => ! $detail->item || ! $detail->item->jubelio_item_id || $detail->item->jubelio_item_id < 1
        )->count();

        return [
            'can_sync' => $canSync,
            'sync_cek' => $syncCek,
            'jubelio_a' => $jubelioA,
            'jubelio_b' => $jubelioB,
            'a_synced' => $canSync && (bool) $transaction->a_submit_by,
            'b_synced' => $canSync && (bool) $transaction->b_submit_by,
            'is_from_jubelio' => $transaction->isFromJubelio(),
            'mapping_missing' => $mappingMissing,
            'adjust_type_a' => $adjustTypeA,
            'adjust_type_b' => $adjustTypeB,
            'warning_a' => $transaction->hasSyncWarningA(),
            'warning_b' => $transaction->hasSyncWarningB(),
            'wh_a_name' => $transaction->sender->name ?? '',
            'wh_b_name' => $transaction->receiver->name ?? '',
        ];
    }

    public function applyToTransaction(Transaction $transaction): array
    {
        $presented = $this->present($transaction);

        $transaction->sync_cek = $presented['sync_cek'];
        $transaction->jubelio_a = $presented['jubelio_a'];
        $transaction->jubelio_b = $presented['jubelio_b'];
        $transaction->a_synced = $presented['a_synced'];
        $transaction->b_synced = $presented['b_synced'];
        $transaction->is_from_jubelio = $presented['is_from_jubelio'];

        return $presented;
    }

    /**
     * @return list<int>
     */
    public function syncedWarehouseIds(): array
    {
        return Jubeliosync::query()
            ->pluck('warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function findSyncForWarehouse(int $warehouseId): ?Jubeliosync
    {
        if ($warehouseId < 1) {
            return null;
        }

        return Jubeliosync::where('warehouse_id', $warehouseId)->first();
    }

    /**
     * @param  list<int>  $syncedWarehouseIds
     */
    private function isSyncedWarehouse(int $warehouseId, array $syncedWarehouseIds): bool
    {
        return $warehouseId > 0 && in_array($warehouseId, $syncedWarehouseIds, true);
    }

    private function resolveSyncCek(int $type, bool $senderSynced, bool $receiverSynced): ?string
    {
        if (in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER], true)) {
            return $senderSynced ? 'S' : null;
        }

        if (in_array($type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN], true)) {
            return $receiverSynced ? 'R' : null;
        }

        if ($type === Transaction::TYPE_MOVE) {
            return match (true) {
                $senderSynced && $receiverSynced => 'B',
                $senderSynced => 'S',
                $receiverSynced => 'R',
                default => null,
            };
        }

        return null;
    }
}
