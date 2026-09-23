<?php

namespace App\Services\Jubelio;

use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WarehouseJubelioStockService;
use Illuminate\Support\Collection;

class JubelioTransactionSyncPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Transaction $transaction, ?Collection $syncMap = null): array
    {
        $syncMap ??= $this->syncMap();
        $state = $this->computeSyncState($transaction, $syncMap);

        $transaction->loadMissing(['sender', 'receiver', 'submitByA', 'submitByB', 'details.item']);

        $mappingMissing = $transaction->relationLoaded('details')
            ? $transaction->details->filter(
                fn ($detail) => ! $detail->item || ! $detail->item->jubelio_item_id || $detail->item->jubelio_item_id < 1
            )->count()
            : 0;

        return array_merge($state, [
            'mapping_missing' => $mappingMissing,
            'warning_a' => $transaction->hasSyncWarningA(),
            'warning_b' => $transaction->hasSyncWarningB(),
            'wh_a_name' => $transaction->sender->name ?? '',
            'wh_b_name' => $transaction->receiver->name ?? '',
        ]);
    }

    /**
     * Lightweight sync_cek for transaction sync list rows (no relation or detail queries).
     */
    public function syncCekForList(Transaction $transaction, Collection $syncMap): ?string
    {
        return $this->computeSyncState($transaction, $syncMap)['sync_cek'];
    }

    public function applyToTransaction(Transaction $transaction, ?Collection $syncMap = null): array
    {
        $syncMap ??= $this->syncMap();
        $presented = $this->present($transaction, $syncMap);

        $transaction->sync_cek = $presented['sync_cek'];
        $transaction->jubelio_a = $presented['jubelio_a'];
        $transaction->jubelio_b = $presented['jubelio_b'];
        $transaction->a_synced = $presented['a_synced'];
        $transaction->b_synced = $presented['b_synced'];
        $transaction->is_from_jubelio = $presented['is_from_jubelio'];

        return $presented;
    }

    /**
     * Whether stock-sync push controls should render for this transaction.
     *
     * @param  array<string, mixed>  $presented
     */
    public function showSyncUi(array $presented, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return config('services.jubelio.active')
            && ($presented['can_sync'] ?? false)
            && (bool) ($presented['sync_cek'] ?? null)
            && Transaction::userCanJubelioTransactionSync($user);
    }

    /**
     * @return Collection<int, Jubeliosync>
     */
    public function syncMap(): Collection
    {
        return Jubeliosync::query()
            ->get()
            ->keyBy(fn (Jubeliosync $row) => (int) $row->warehouse_id);
    }

    /**
     * Jubelio mapping hints for the transaction create form (item load warnings).
     * Uses jubeliosyncs.warehouse_id only — no Jubelio API calls.
     *
     * @return array{synced_warehouse_ids: list<int>}
     */
    public function createFormSyncConfig(): array
    {
        return [
            'synced_warehouse_ids' => Jubeliosync::query()
                ->pluck('warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * Live Jubelio available qty per item at mapped warehouse locations (detail-sync table).
     *
     * @param  array<string, mixed>  $presented  Output from {@see present()}
     * @return array{
     *     show_jubelio_sender_qty: bool,
     *     show_jubelio_receiver_qty: bool,
     *     jubelio_qty_sender: array<int, float|null>,
     *     jubelio_qty_receiver: array<int, float|null>,
     *     jubelio_qty_fetch_failed: bool,
     * }
     */
    public function detailSyncJubelioQuantities(
        Transaction $transaction,
        array $presented,
        WarehouseJubelioStockService $stockService,
    ): array {
        $type = (int) $transaction->type;
        $showSender = in_array($type, JubelioStockSync::senderPushTypes(), true)
            && filled($presented['jubelio_a'] ?? null);
        $showReceiver = in_array($type, JubelioStockSync::receiverPushTypes(), true)
            && filled($presented['jubelio_b'] ?? null);

        $senderQty = [];
        $receiverQty = [];
        $fetchFailed = false;

        if (! config('services.jubelio.active') || (! $showSender && ! $showReceiver)) {
            return [
                'show_jubelio_sender_qty' => $showSender,
                'show_jubelio_receiver_qty' => $showReceiver,
                'jubelio_qty_sender' => $senderQty,
                'jubelio_qty_receiver' => $receiverQty,
                'jubelio_qty_fetch_failed' => false,
            ];
        }

        $items = $transaction->details
            ->map(fn ($detail) => $detail->item)
            ->filter(fn ($item) => $item instanceof Item)
            ->unique('id')
            ->values();

        if ($showSender) {
            $sync = $stockService->syncForWarehouse((int) $transaction->sender_id);
            if ($sync) {
                $data = $stockService->stockDataForItems($sync, $items);
                $fetchFailed = $fetchFailed || $data['fetch_failed'];
                foreach ($data['stocks'] as $itemId => $row) {
                    $senderQty[$itemId] = $row['available'] ?? $row['on_hand'];
                }
            }
        }

        if ($showReceiver) {
            $sync = $stockService->syncForWarehouse((int) $transaction->receiver_id);
            if ($sync) {
                $data = $stockService->stockDataForItems($sync, $items);
                $fetchFailed = $fetchFailed || $data['fetch_failed'];
                foreach ($data['stocks'] as $itemId => $row) {
                    $receiverQty[$itemId] = $row['available'] ?? $row['on_hand'];
                }
            }
        }

        return [
            'show_jubelio_sender_qty' => $showSender,
            'show_jubelio_receiver_qty' => $showReceiver,
            'jubelio_qty_sender' => $senderQty,
            'jubelio_qty_receiver' => $receiverQty,
            'jubelio_qty_fetch_failed' => $fetchFailed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function computeSyncState(Transaction $transaction, Collection $syncMap): array
    {
        $canSync = $transaction->isManual();
        $type = (int) $transaction->type;
        $senderId = (int) $transaction->sender_id;
        $receiverId = (int) $transaction->receiver_id;

        $syncRelevantA = in_array($type, JubelioStockSync::senderPushTypes(), true);
        $syncRelevantB = in_array($type, JubelioStockSync::receiverPushTypes(), true);

        $jubSyncA = $syncMap->get($senderId);
        $jubSyncB = $syncMap->get($receiverId);
        $senderSynced = $syncRelevantA && $syncMap->has($senderId);
        $receiverSynced = $syncRelevantB && $syncMap->has($receiverId);

        $syncCek = $canSync ? $this->resolveSyncCek($type, $senderSynced, $receiverSynced) : null;

        return [
            'can_sync' => $canSync,
            'sync_cek' => $syncCek,
            'jubelio_a' => ($canSync && $senderSynced && $jubSyncA) ? $jubSyncA->jubelio_location_name : null,
            'jubelio_b' => ($canSync && $receiverSynced && $jubSyncB) ? $jubSyncB->jubelio_location_name : null,
            'location_id_a' => ($canSync && $senderSynced && $jubSyncA) ? (int) $jubSyncA->jubelio_location_id : null,
            'location_id_b' => ($canSync && $receiverSynced && $jubSyncB) ? (int) $jubSyncB->jubelio_location_id : null,
            'bin_a' => ($canSync && $senderSynced && $jubSyncA) ? (int) $jubSyncA->bin_id : null,
            'bin_b' => ($canSync && $receiverSynced && $jubSyncB) ? (int) $jubSyncB->bin_id : null,
            'a_synced' => $canSync && (bool) $transaction->a_submit_by,
            'b_synced' => $canSync && (bool) $transaction->b_submit_by,
            'is_from_jubelio' => $transaction->isFromJubelio(),
            'adjust_type_a' => ($senderSynced && $jubSyncA) ? JubelioStockSync::ADJUST_DEDUCT : 0,
            'adjust_type_b' => ($receiverSynced && $jubSyncB) ? JubelioStockSync::ADJUST_ADD : 0,
        ];
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
