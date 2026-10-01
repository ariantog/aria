<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JubelioLegacyCatchupStockRestoreService
{
    public function __construct(private TransactionService $transactionService) {}

    public function transactionDateBefore(): string
    {
        return (string) config('jubelio_legacy_catchup.transaction_date_before', '2025-12-31');
    }

    public function createdAfter(): string
    {
        return (string) config('jubelio_legacy_catchup.created_after', '2026-06-30 20:39:03');
    }

    public function restoreNotePrefix(): string
    {
        return (string) config('jubelio_legacy_catchup.restore_note_prefix', 'jubelio-legacy-catchup-restore:');
    }

    public function cronUserId(): int
    {
        return (int) config('jubelio_legacy_catchup.cron_user_id', Transaction::JUBELIO_CRON_USER_ID);
    }

    /**
     * @return Builder<Transaction>
     */
    public function problematicSellQuery(): Builder
    {
        return Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('user_id', $this->cronUserId())
            ->where('submit_type', Transaction::SUBMIT_TYPE_JUBELIO)
            ->whereDate('date', '<', $this->transactionDateBefore())
            ->where('created_at', '>', $this->createdAfter());
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateProblematicSells(?int $warehouseId = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->problematicSellQuery()
            ->when($warehouseId, fn (Builder $q) => $q->where('sender_id', $warehouseId))
            ->with(['sender', 'receiver', 'details.item'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Addrbook>
     */
    public function warehousesOnProblematicSells(): Collection
    {
        $ids = $this->problematicSellQuery()
            ->distinct()
            ->pluck('sender_id');

        return Addrbook::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<Addrbook>
     */
    public function virtualWarehouses(): array
    {
        return Addrbook::query()
            ->where('type', Addrbook::TYPE_V_WAREHOUSE)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function alreadyRestoredSellIds(array $sellIds): array
    {
        if ($sellIds === []) {
            return [];
        }

        $prefix = $this->restoreNotePrefix();
        $restored = [];

        foreach ($sellIds as $sellId) {
            $sellId = (int) $sellId;
            $needle = $prefix.$sellId;
            $exists = Transaction::query()
                ->where('type', Transaction::TYPE_MOVE)
                ->where('notes', 'like', $needle.'%')
                ->exists();
            if ($exists) {
                $restored[] = $sellId;
            }
        }

        return $restored;
    }

    /**
     * @param  list<int>  $sellTransactionIds
     * @return array{
     *     restored: list<array{sell_id: int, move_id: int, invoice: string|null}>,
     *     skipped: list<array{sell_id: int, reason: string}>,
     *     errors: list<array{sell_id: int, message: string}>
     * }
     */
    public function restoreStockForSells(array $sellTransactionIds, int $virtualWarehouseId, int $actingUserId): array
    {
        $virtual = Addrbook::find($virtualWarehouseId);
        if (! $virtual || $virtual->typeValue() !== Addrbook::TYPE_V_WAREHOUSE) {
            throw new \InvalidArgumentException('Virtual warehouse tidak valid.');
        }

        $restored = [];
        $skipped = [];
        $errors = [];

        $sellIds = array_values(array_unique(array_map('intval', $sellTransactionIds)));

        foreach ($sellIds as $sellId) {
            if ($sellId <= 0) {
                continue;
            }

            try {
                $result = $this->restoreOneSell($sellId, $virtual, $actingUserId);
                if ($result['status'] === 'restored') {
                    $restored[] = $result['payload'];
                } else {
                    $skipped[] = ['sell_id' => $sellId, 'reason' => $result['reason']];
                }
            } catch (\Throwable $e) {
                $errors[] = ['sell_id' => $sellId, 'message' => $e->getMessage()];
            }
        }

        return compact('restored', 'skipped', 'errors');
    }

    /**
     * @return array{status: string, reason?: string, payload?: array{sell_id: int, move_id: int, invoice: string|null}}
     */
    protected function restoreOneSell(int $sellId, Addrbook $virtualWarehouse, int $actingUserId): array
    {
        $sell = $this->problematicSellQuery()
            ->with('details')
            ->whereKey($sellId)
            ->first();

        if (! $sell) {
            return ['status' => 'skipped', 'reason' => 'Bukan transaksi sell bermasalah (tidak lolos filter).'];
        }

        if ($this->alreadyRestoredSellIds([$sellId]) !== []) {
            return ['status' => 'skipped', 'reason' => 'Stok sudah pernah dikembalikan (move ada).'];
        }

        $physical = Addrbook::find($sell->sender_id);
        if (! $physical || ! Addrbook::typeIsWarehouse($physical->typeValue())) {
            return ['status' => 'skipped', 'reason' => 'Gudang pengirim sell tidak valid.'];
        }

        if ($sell->details->isEmpty()) {
            return ['status' => 'skipped', 'reason' => 'Sell tanpa baris detail.'];
        }

        return DB::transaction(function () use ($sell, $virtualWarehouse, $physical, $actingUserId) {
            if ($this->alreadyRestoredSellIds([(int) $sell->id]) !== []) {
                return ['status' => 'skipped', 'reason' => 'Stok sudah pernah dikembalikan (move ada).'];
            }

            $moveDate = Carbon::today();
            $note = $this->restoreNotePrefix().$sell->id.' from sell invoice '.($sell->invoice ?? '—');

            $move = Transaction::create([
                'date' => $moveDate,
                'type' => Transaction::TYPE_MOVE,
                'sender_type' => (string) $virtualWarehouse->typeValue(),
                'sender_id' => $virtualWarehouse->id,
                'receiver_type' => (string) $physical->typeValue(),
                'receiver_id' => $physical->id,
                'notes' => $note,
                'user_id' => $actingUserId,
                'status' => Transaction::STATUS_COMPLETED,
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                'invoice' => 'RESTORE-SELL-'.$sell->id,
            ]);

            $itemsTotal = 0.0;
            $qtyTotal = 0.0;

            foreach ($sell->details as $detail) {
                $qty = (float) $detail->quantity;
                if ($qty <= 0) {
                    continue;
                }
                $price = (float) $detail->price;
                $discountPercent = max(0.0, min(100.0, (float) ($detail->discount ?? 0)));
                $gross = $qty * $price;
                $lineTotal = $gross - ($gross * $discountPercent / 100);

                TransactionDetail::create([
                    'transaction_id' => $move->id,
                    'date' => $moveDate,
                    'transaction_type' => Transaction::TYPE_MOVE,
                    'sender_id' => $virtualWarehouse->id,
                    'receiver_id' => $physical->id,
                    'item_id' => $detail->item_id,
                    'quantity' => $qty,
                    'price' => $price,
                    'discount' => $detail->discount ?? 0,
                    'total' => $lineTotal,
                    'notes' => 'Restore from sell #'.$sell->id,
                ]);

                $itemsTotal += $lineTotal;
                $qtyTotal += $qty;
            }

            if ($qtyTotal <= 0) {
                throw new \RuntimeException('Tidak ada qty pada detail sell.');
            }

            $move->update([
                'total' => Transaction::signedAmount(Transaction::TYPE_MOVE, $itemsTotal),
                'total_items' => $qtyTotal,
            ]);

            $this->transactionService->handleTransaction($move->fresh(['details']));

            return [
                'status' => 'restored',
                'payload' => [
                    'sell_id' => (int) $sell->id,
                    'move_id' => (int) $move->id,
                    'invoice' => $sell->invoice,
                ],
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    public function restoredMoveLinksForPage(Collection $sells): array
    {
        $map = [];
        foreach ($sells as $sell) {
            $prefix = $this->restoreNotePrefix().$sell->id;
            $moveId = Transaction::query()
                ->where('type', Transaction::TYPE_MOVE)
                ->where('notes', 'like', $prefix.'%')
                ->value('id');
            if ($moveId) {
                $map[(int) $sell->id] = (string) $moveId;
            }
        }

        return $map;
    }
}
