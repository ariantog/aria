<?php

namespace App\Services\Jubelio;

use App\Models\Jubeliosync;
use App\Models\Transaction;

class JubelioTransactionSyncPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Transaction $transaction): array
    {
        $transaction->loadMissing(['sender', 'receiver', 'submitByA', 'submitByB', 'details.item']);

        $isManual = $transaction->submit_type === Transaction::SUBMIT_TYPE_MANUAL;
        $type = (int) $transaction->type;

        $syncRelevantA = in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER, Transaction::TYPE_MOVE], true);
        $syncRelevantB = in_array($type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN, Transaction::TYPE_MOVE], true);
        $syncedWarehouseIds = Jubeliosync::pluck('warehouse_id')->toArray();

        $jubSyncA = Jubeliosync::where('warehouse_id', $transaction->sender_id)->first();
        $jubSyncB = Jubeliosync::where('warehouse_id', $transaction->receiver_id)->first();

        $jubelioA = ($isManual && $jubSyncA && $syncRelevantA) ? $jubSyncA->jubelio_location_name : null;
        $jubelioB = ($isManual && $jubSyncB && $syncRelevantB) ? $jubSyncB->jubelio_location_name : null;

        $syncCek = null;
        if ($isManual) {
            if (in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER], true)) {
                $syncCek = in_array($transaction->sender_id, $syncedWarehouseIds, true) ? 'S' : null;
            } elseif (in_array($type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN], true)) {
                $syncCek = in_array($transaction->receiver_id, $syncedWarehouseIds, true) ? 'R' : null;
            } elseif ($type === Transaction::TYPE_MOVE) {
                $senderSynced = in_array($transaction->sender_id, $syncedWarehouseIds, true);
                $receiverSynced = in_array($transaction->receiver_id, $syncedWarehouseIds, true);
                $syncCek = match (true) {
                    $senderSynced && $receiverSynced => 'B',
                    $senderSynced => 'S',
                    $receiverSynced => 'R',
                    default => null,
                };
            }
        }

        $adjustTypeA = ($jubSyncA && $syncRelevantA) ? 2 : 0;
        $adjustTypeB = ($jubSyncB && $syncRelevantB) ? 1 : 0;

        $mappingMissing = $transaction->details->filter(
            fn ($detail) => ! $detail->item || ! $detail->item->jubelio_item_id || $detail->item->jubelio_item_id < 1
        )->count();

        return [
            'can_sync' => $isManual,
            'sync_cek' => $syncCek,
            'jubelio_a' => $jubelioA,
            'jubelio_b' => $jubelioB,
            'a_synced' => $isManual && (bool) $transaction->a_submit_by,
            'b_synced' => $isManual && (bool) $transaction->b_submit_by,
            'is_from_jubelio' => $transaction->submit_type === Transaction::SUBMIT_TYPE_JUBELIO,
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
}
