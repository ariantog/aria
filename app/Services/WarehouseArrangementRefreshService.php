<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\WarehouseArrangementRefreshJob;
use App\Models\WarehouseItemMonthlyStat;
use App\Jobs\ProcessWarehouseArrangementRefreshBatch;
use App\Services\Items\ItemDimensionResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarehouseArrangementRefreshService
{
    public const BATCH_SIZE = 300;

    public function __construct(
        private ItemDimensionResolver $dimensions,
        private WarehouseArrangementSyncService $arrangementSync,
    ) {}

    public function activeJobForWarehouse(int $destinationWarehouseId): ?WarehouseArrangementRefreshJob
    {
        return WarehouseArrangementRefreshJob::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereIn('status', [
                WarehouseArrangementRefreshJob::STATUS_CREATED,
                WarehouseArrangementRefreshJob::STATUS_PROCESSING,
            ])
            ->orderByDesc('created_at')
            ->first();
    }

    public function lastFinishedJobForWarehouse(int $destinationWarehouseId): ?WarehouseArrangementRefreshJob
    {
        return WarehouseArrangementRefreshJob::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereIn('status', [
                WarehouseArrangementRefreshJob::STATUS_COMPLETED,
                WarehouseArrangementRefreshJob::STATUS_FAILED,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function createJob(int $destinationWarehouseId, ?int $userId): WarehouseArrangementRefreshJob
    {
        if ($this->activeJobForWarehouse($destinationWarehouseId)) {
            throw new \RuntimeException('A refresh job is already queued or running for this warehouse.');
        }

        $itemIds = $this->itemIdsForWarehouse($destinationWarehouseId);

        WarehouseItemMonthlyStat::query()
            ->where('warehouse_id', $destinationWarehouseId)
            ->delete();

        $job = WarehouseArrangementRefreshJob::create([
            'destination_warehouse_id' => $destinationWarehouseId,
            'user_id' => $userId,
            'status' => WarehouseArrangementRefreshJob::STATUS_CREATED,
            'phase' => WarehouseArrangementRefreshJob::PHASE_STATS,
            'item_cursor' => 0,
            'total_items' => count($itemIds),
        ]);

        $this->rememberItemIds($job->id, $itemIds);

        ProcessWarehouseArrangementRefreshBatch::dispatch($job->id);

        return $job;
    }

    /**
     * @return array{done: bool, processed: int, phase: string, message: ?string, status: string, item_cursor: int, total_items: int, progress_percent: int, error_message: ?string}
     */
    public function processUntilDeadline(WarehouseArrangementRefreshJob $job, int $seconds = 25): array
    {
        $deadline = microtime(true) + $seconds;
        $totalProcessed = 0;
        $lastResult = [
            'done' => false,
            'processed' => 0,
            'phase' => $job->phase,
            'message' => null,
        ];

        while (microtime(true) < $deadline) {
            $job->refresh();

            if (! $job->isActive()) {
                break;
            }

            $lastResult = $this->processNextBatch($job);
            $totalProcessed += $lastResult['processed'];
            $job->refresh();

            if ($lastResult['done'] || ! $job->isActive()) {
                break;
            }

            if ($job->phase === WarehouseArrangementRefreshJob::PHASE_SYNC) {
                break;
            }
        }

        $job->refresh();

        return array_merge($lastResult, [
            'processed' => $totalProcessed,
            'done' => ! $job->isActive() || $lastResult['done'],
            'status' => $job->status,
            'item_cursor' => $job->item_cursor,
            'total_items' => $job->total_items,
            'progress_percent' => $job->progressPercent(),
            'error_message' => $job->error_message,
        ]);
    }

    public function jobProgressPayload(WarehouseArrangementRefreshJob $job): array
    {
        return [
            'active' => $job->isActive(),
            'done' => ! $job->isActive(),
            'failed' => $job->status === WarehouseArrangementRefreshJob::STATUS_FAILED,
            'status' => $job->status,
            'phase' => $job->phase,
            'item_cursor' => $job->item_cursor,
            'total_items' => $job->total_items,
            'progress_percent' => $job->progressPercent(),
            'error_message' => $job->error_message,
            'result_message' => $job->result_message,
        ];
    }

    /**
     * @return array{done: bool, processed: int, phase: string, message: ?string}
     */
    public function processNextBatch(WarehouseArrangementRefreshJob $job, int $batchSize = self::BATCH_SIZE): array
    {
        if (! $job->isActive()) {
            return ['done' => true, 'processed' => 0, 'phase' => $job->phase, 'message' => null];
        }

        if ($job->status === WarehouseArrangementRefreshJob::STATUS_CREATED) {
            $job->update([
                'status' => WarehouseArrangementRefreshJob::STATUS_PROCESSING,
                'started_at' => now(),
            ]);
            $job->refresh();
        }

        if ($job->phase === WarehouseArrangementRefreshJob::PHASE_STATS) {
            $result = $this->processStatsBatch($job, $batchSize);
            $job->refresh();

            if ($job->phase === WarehouseArrangementRefreshJob::PHASE_SYNC && $job->isActive()) {
                return $this->processSyncPhase($job);
            }

            return $result;
        }

        return $this->processSyncPhase($job);
    }

    /**
     * @return array{done: bool, processed: int, phase: string, message: ?string}
     */
    private function processStatsBatch(WarehouseArrangementRefreshJob $job, int $batchSize): array
    {
        $warehouseId = (int) $job->destination_warehouse_id;
        $itemIds = $this->itemIdsForJob($job);

        if ($itemIds === []) {
            $job->update(['phase' => WarehouseArrangementRefreshJob::PHASE_SYNC]);

            return [
                'done' => false,
                'processed' => 0,
                'phase' => WarehouseArrangementRefreshJob::PHASE_SYNC,
                'message' => 'No transaction history for stats rebuild; continuing to cache sync.',
            ];
        }

        $batch = array_slice($itemIds, $job->item_cursor, $batchSize);

        if ($batch === []) {
            $job->update(['phase' => WarehouseArrangementRefreshJob::PHASE_SYNC]);

            return [
                'done' => false,
                'processed' => 0,
                'phase' => WarehouseArrangementRefreshJob::PHASE_SYNC,
                'message' => null,
            ];
        }

        $inserted = $this->rebuildStatsForItems($warehouseId, $batch);

        $newCursor = min($job->item_cursor + count($batch), count($itemIds));
        $updates = [
            'item_cursor' => $newCursor,
            'stats_rows_inserted' => $job->stats_rows_inserted + $inserted,
        ];

        if ($newCursor >= count($itemIds)) {
            $updates['phase'] = WarehouseArrangementRefreshJob::PHASE_SYNC;
        }

        $job->update($updates);

        return [
            'done' => false,
            'processed' => count($batch),
            'phase' => $updates['phase'] ?? WarehouseArrangementRefreshJob::PHASE_STATS,
            'message' => null,
        ];
    }

    /**
     * @return array{done: bool, processed: int, phase: string, message: ?string}
     */
    private function processSyncPhase(WarehouseArrangementRefreshJob $job): array
    {
        if (! $this->arrangementSync->arrangementTablesExist()) {
            $this->failJob($job, 'Warehouse arrangement cache tables are missing. Run php artisan migrate first.');

            return [
                'done' => true,
                'processed' => 0,
                'phase' => $job->phase,
                'message' => $job->error_message,
            ];
        }

        $result = $this->arrangementSync->syncAll((int) $job->destination_warehouse_id);

        $message = sprintf(
            'Rebuilt %d monthly stat row(s) and refreshed arrangement cache (%d candidate SKU(s), %d source link(s)).',
            $job->stats_rows_inserted,
            $result['candidates'],
            $result['sources'],
        );

        $job->update([
            'status' => WarehouseArrangementRefreshJob::STATUS_COMPLETED,
            'sync_candidates' => $result['candidates'],
            'sync_sources' => $result['sources'],
            'result_message' => $message,
            'completed_at' => now(),
        ]);

        return [
            'done' => true,
            'processed' => 0,
            'phase' => WarehouseArrangementRefreshJob::PHASE_SYNC,
            'message' => $message,
        ];
    }

    public function failJob(WarehouseArrangementRefreshJob $job, string $message): void
    {
        $job->update([
            'status' => WarehouseArrangementRefreshJob::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        Cache::forget($this->itemIdsCacheKey($job->id));
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function rememberItemIds(int $jobId, array $itemIds): void
    {
        Cache::put($this->itemIdsCacheKey($jobId), $itemIds, now()->addDay());
    }

    private function itemIdsCacheKey(int $jobId): string
    {
        return 'warehouse-arrangement-refresh-items:'.$jobId;
    }

    /**
     * @return list<int>
     */
    private function itemIdsForJob(WarehouseArrangementRefreshJob $job): array
    {
        $cached = Cache::get($this->itemIdsCacheKey($job->id));
        if (is_array($cached)) {
            return array_values(array_map('intval', $cached));
        }

        $itemIds = $this->itemIdsForWarehouse((int) $job->destination_warehouse_id);
        $this->rememberItemIds($job->id, $itemIds);

        if ($job->total_items !== count($itemIds)) {
            $job->update(['total_items' => count($itemIds)]);
        }

        return $itemIds;
    }

    /**
     * @return list<int>
     */
    private function itemIdsForWarehouse(int $warehouseId): array
    {
        $sellType = Transaction::TYPE_SELL;
        $returnType = Transaction::TYPE_RETURN;

        $sellIds = DB::table('transaction_details')
            ->where('transaction_type', $sellType)
            ->where('sender_id', $warehouseId)
            ->where('sender_id', '>', 0)
            ->distinct()
            ->pluck('item_id');

        $returnIds = DB::table('transaction_details')
            ->where('transaction_type', $returnType)
            ->where('receiver_id', $warehouseId)
            ->where('receiver_id', '>', 0)
            ->distinct()
            ->pluck('item_id');

        return $sellIds->merge($returnIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function rebuildStatsForItems(int $warehouseId, array $itemIds): int
    {
        if ($itemIds === []) {
            return 0;
        }

        $sellType = Transaction::TYPE_SELL;
        $returnType = Transaction::TYPE_RETURN;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $monthExpr = 'CAST(strftime(\'%m\', transaction_details.date) AS INTEGER)';
            $yearExpr = 'CAST(strftime(\'%Y\', transaction_details.date) AS INTEGER)';
        } else {
            $monthExpr = 'MONTH(transaction_details.date)';
            $yearExpr = 'YEAR(transaction_details.date)';
        }

        $stats = [];

        $sellRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.transaction_type', $sellType)
            ->where('transaction_details.sender_id', $warehouseId)
            ->whereIn('transaction_details.item_id', $itemIds)
            ->selectRaw("transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($sellRows as $row) {
            $this->accumulateStat($stats, $warehouseId, $row, 'sold_qty', 'sold_value', (float) $row->qty, (float) $row->value);
        }

        $returnRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.transaction_type', $returnType)
            ->where('transaction_details.receiver_id', $warehouseId)
            ->whereIn('transaction_details.item_id', $itemIds)
            ->selectRaw("transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($returnRows as $row) {
            $this->accumulateStat($stats, $warehouseId, $row, 'returned_qty', 'returned_value', (float) $row->qty, (float) $row->value);
        }

        if ($stats === []) {
            return 0;
        }

        $resolved = $this->dimensions->resolveMany(array_values(array_unique(array_column($stats, 'item_id'))));
        $now = now();
        $insertRows = [];

        foreach ($stats as $stat) {
            $dims = $resolved[$stat['item_id']] ?? null;
            if (! $dims) {
                continue;
            }

            $insertRows[] = array_merge($dims, [
                'warehouse_id' => $stat['warehouse_id'],
                'item_id' => $stat['item_id'],
                'month' => $stat['month'],
                'year' => $stat['year'],
                'sold_qty' => $stat['sold_qty'],
                'sold_value' => $stat['sold_value'],
                'returned_qty' => $stat['returned_qty'],
                'returned_value' => $stat['returned_value'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($insertRows === []) {
            return 0;
        }

        foreach (array_chunk($insertRows, 500) as $chunk) {
            DB::table('warehouse_item_monthly_stats')->insert($chunk);
        }

        return count($insertRows);
    }

    /**
     * @param  array<string, array<string, mixed>>  $stats
     */
    private function accumulateStat(
        array &$stats,
        int $warehouseId,
        object $row,
        string $qtyColumn,
        string $valueColumn,
        float $qty,
        float $value,
    ): void {
        if ($qty <= 0 && $value <= 0) {
            return;
        }

        $key = $warehouseId.'|'.$row->item_id.'|'.$row->month.'|'.$row->year;

        if (! isset($stats[$key])) {
            $stats[$key] = [
                'warehouse_id' => $warehouseId,
                'item_id' => (int) $row->item_id,
                'month' => (int) $row->month,
                'year' => (int) $row->year,
                'sold_qty' => 0.0,
                'sold_value' => 0.0,
                'returned_qty' => 0.0,
                'returned_value' => 0.0,
            ];
        }

        $stats[$key][$qtyColumn] += $qty;
        $stats[$key][$valueColumn] += $value;
    }
}
