<?php

namespace App\Services\InventoryHealth;

use App\Models\Addrbook;
use App\Models\InventoryHealthSnapshot;
use App\Services\Items\ItemDimensionResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryHealthSyncService
{
    public const COMPANY_WAREHOUSE_ID = 0;

    /**
     * Rebuild snapshots from warehouse_item_monthly_stats + current warehouse_item stock.
     * One warehouse at a time so this stays bounded on production.
     *
     * @return array{warehouses: int, rows: int, synced_at: string}
     */
    public function syncAll(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $windows = $this->windows($asOf);
        $startPeriod = ItemDimensionResolver::periodStartKey(30);
        $startExtended = ItemDimensionResolver::periodStartKey(90);
        $syncedAt = $asOf->copy();

        $warehouseIds = Addrbook::query()
            ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = 0;
        DB::transaction(function () use ($warehouseIds, $windows, $startPeriod, $startExtended, $syncedAt, &$rows) {
            InventoryHealthSnapshot::query()->delete();

            foreach ($warehouseIds as $warehouseId) {
                $rows += $this->syncWarehouse($warehouseId, $windows, $startPeriod, $startExtended, $syncedAt);
            }

            $rows += $this->syncCompany($windows, $syncedAt);
        });

        return [
            'warehouses' => count($warehouseIds),
            'rows' => $rows,
            'synced_at' => $syncedAt->toDateTimeString(),
        ];
    }

    /**
     * @return array{period_from: string, period_to: string, extended_from: string, period_days: int}
     */
    public function windows(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $to = $asOf->toDateString();
        $from = $asOf->copy()->subDays(30)->toDateString();

        return [
            'period_from' => $from,
            'period_to' => $to,
            'extended_from' => $asOf->copy()->subDays(90)->toDateString(),
            'period_days' => 30,
        ];
    }

    public function latestSyncedAt(): ?CarbonInterface
    {
        $value = InventoryHealthSnapshot::query()->max('synced_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     */
    private function syncWarehouse(
        int $warehouseId,
        array $windows,
        int $startPeriod,
        int $startExtended,
        CarbonInterface $syncedAt,
    ): int {
        $sales = $this->salesByItem($warehouseId, $startPeriod, $startExtended);
        $stock = $this->stockByItem($warehouseId);
        $itemIds = array_values(array_unique([...array_keys($sales), ...array_keys($stock)]));

        $payload = [];
        foreach ($itemIds as $itemId) {
            $sale = $sales[$itemId] ?? null;
            $currentStock = (float) ($stock[$itemId] ?? 0);
            $soldPeriod = (float) ($sale['sold_period'] ?? 0);
            $returnedPeriod = (float) ($sale['returned_period'] ?? 0);
            $soldExtended = (float) ($sale['sold_extended'] ?? 0);
            $returnedExtended = (float) ($sale['returned_extended'] ?? 0);

            if ($currentStock <= 0.0 && $soldPeriod <= 0.0 && $returnedPeriod <= 0.0 && $soldExtended <= 0.0 && $returnedExtended <= 0.0) {
                continue;
            }

            $payload[] = $this->row(
                $warehouseId,
                $itemId,
                $soldPeriod,
                $returnedPeriod,
                $soldExtended,
                $returnedExtended,
                $currentStock,
                $sale['last_sold_at'] ?? null,
                $windows,
                $syncedAt,
            );
        }

        $this->insertChunks($payload);

        return count($payload);
    }

    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     */
    private function syncCompany(array $windows, CarbonInterface $syncedAt): int
    {
        $aggregates = InventoryHealthSnapshot::query()
            ->where('warehouse_id', '>', self::COMPANY_WAREHOUSE_ID)
            ->selectRaw('item_id')
            ->selectRaw('SUM(sold_period) as sold_period')
            ->selectRaw('SUM(returned_period) as returned_period')
            ->selectRaw('SUM(sold_extended) as sold_extended')
            ->selectRaw('SUM(returned_extended) as returned_extended')
            ->selectRaw('SUM(current_stock) as current_stock')
            ->selectRaw('MAX(last_sold_at) as last_sold_at')
            ->groupBy('item_id')
            ->get();

        $payload = [];
        foreach ($aggregates as $row) {
            $payload[] = $this->row(
                self::COMPANY_WAREHOUSE_ID,
                (int) $row->item_id,
                (float) $row->sold_period,
                (float) $row->returned_period,
                (float) $row->sold_extended,
                (float) $row->returned_extended,
                (float) $row->current_stock,
                $row->last_sold_at,
                $windows,
                $syncedAt,
            );
        }

        $this->insertChunks($payload);

        return count($payload);
    }

    /**
     * @return array<int, array{sold_period: float, returned_period: float, sold_extended: float, returned_extended: float, last_sold_at: ?string}>
     */
    private function salesByItem(int $warehouseId, int $startPeriod, int $startExtended): array
    {
        $rows = DB::table('warehouse_item_monthly_stats')
            ->where('warehouse_id', $warehouseId)
            ->whereRaw('(year * 12 + month) >= ?', [$startExtended])
            ->select('item_id')
            ->selectRaw(
                'SUM(CASE WHEN (year * 12 + month) >= ? THEN sold_qty ELSE 0 END) as sold_period',
                [$startPeriod],
            )
            ->selectRaw(
                'SUM(CASE WHEN (year * 12 + month) >= ? THEN returned_qty ELSE 0 END) as returned_period',
                [$startPeriod],
            )
            ->selectRaw('SUM(sold_qty) as sold_extended')
            ->selectRaw('SUM(returned_qty) as returned_extended')
            ->selectRaw('MAX(CASE WHEN sold_qty > 0 THEN (year * 100 + month) ELSE NULL END) as last_sold_ym')
            ->groupBy('item_id')
            ->get();

        $sales = [];
        foreach ($rows as $row) {
            $sales[(int) $row->item_id] = [
                'sold_period' => (float) $row->sold_period,
                'returned_period' => (float) $row->returned_period,
                'sold_extended' => (float) $row->sold_extended,
                'returned_extended' => (float) $row->returned_extended,
                'last_sold_at' => $this->yearMonthToDate($row->last_sold_ym ?? null),
            ];
        }

        return $sales;
    }

    /**
     * @return array<int, float>
     */
    private function stockByItem(int $warehouseId): array
    {
        return DB::table('warehouse_item')
            ->where('warehouse_id', $warehouseId)
            ->select('item_id', DB::raw('COALESCE(SUM(quantity), 0) as current_stock'))
            ->groupBy('item_id')
            ->pluck('current_stock', 'item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    /**
     * @param  array{period_from: string, period_to: string, extended_from: string, period_days: int}  $windows
     * @return array<string, mixed>
     */
    private function row(
        int $warehouseId,
        int $itemId,
        float $soldPeriod,
        float $returnedPeriod,
        float $soldExtended,
        float $returnedExtended,
        float $stock,
        mixed $lastSoldAt,
        array $windows,
        CarbonInterface $syncedAt,
    ): array {
        return [
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'sold_period' => $soldPeriod,
            'returned_period' => $returnedPeriod,
            'sold_extended' => $soldExtended,
            'returned_extended' => $returnedExtended,
            'current_stock' => $stock,
            'last_sold_at' => $lastSoldAt,
            'period_from' => $windows['period_from'],
            'period_to' => $windows['period_to'],
            'extended_from' => $windows['extended_from'],
            'period_days' => $windows['period_days'],
            'synced_at' => $syncedAt,
            'created_at' => $syncedAt,
            'updated_at' => $syncedAt,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    private function insertChunks(array $payload): void
    {
        foreach (array_chunk($payload, 500) as $chunk) {
            InventoryHealthSnapshot::query()->insert($chunk);
        }
    }

    private function yearMonthToDate(mixed $yearMonth): ?string
    {
        $value = (int) $yearMonth;
        if ($value < 200001) {
            return null;
        }

        $year = intdiv($value, 100);
        $month = $value % 100;
        if ($month < 1 || $month > 12) {
            return null;
        }

        return sprintf('%04d-%02d-01', $year, $month);
    }
}
