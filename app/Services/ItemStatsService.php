<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Transaction;
use App\Services\Items\ItemDimensionResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemStatsService
{
    /**
     * @return array<int, string>
     */
    public static function periodOptions(): array
    {
        return ProductPerformanceService::periodOptions();
    }

    public function normalizePeriodDays(int|string|null $raw): int
    {
        $period = (int) $raw;

        return in_array($period, ItemDimensionResolver::validPeriods(), true) ? $period : 90;
    }

    public function warehouses(): Collection
    {
        return Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Monthly sell/return breakdown for a single SKU from live transaction_details.
     *
     * Values apply the invoice header discount the same way warehouse monthly
     * stats do: line total × (100 − discount%) / 100.
     *
     * @return array{
     *     months: list<array{
     *         label: string,
     *         year: int,
     *         month: int,
     *         sold_qty: float,
     *         returned_qty: float,
     *         net_qty: float,
     *         sold_value: float,
     *         returned_value: float,
     *         net_value: float
     *     }>,
     *     totals: array<string, float>,
     *     has_data: bool
     * }
     */
    public function monthlyBreakdown(int $itemId, int $periodDays = 90, ?int $warehouseId = null): array
    {
        $periodDays = $this->normalizePeriodDays($periodDays);
        $startDate = now()->subDays($periodDays)->toDateString();
        if ($startDate < WarehouseItemStatsRebuilder::EARLIEST_SUPPORTED_DATE) {
            $startDate = WarehouseItemStatsRebuilder::EARLIEST_SUPPORTED_DATE;
        }

        $sell = Transaction::TYPE_SELL;
        $return = Transaction::TYPE_RETURN;

        $rows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.item_id', $itemId)
            ->whereIn('transaction_details.transaction_type', [$sell, $return])
            ->where('transaction_details.date', '>=', $startDate)
            ->when($warehouseId, function ($query) use ($warehouseId, $sell, $return) {
                $query->where(function ($query) use ($warehouseId, $sell, $return) {
                    $query->where(function ($query) use ($warehouseId, $sell) {
                        $query->where('transaction_details.transaction_type', $sell)
                            ->where('transaction_details.sender_id', $warehouseId);
                    })->orWhere(function ($query) use ($warehouseId, $return) {
                        $query->where('transaction_details.transaction_type', $return)
                            ->where('transaction_details.receiver_id', $warehouseId);
                    });
                });
            })
            ->get([
                'transaction_details.date',
                'transaction_details.transaction_type',
                'transaction_details.quantity',
                'transaction_details.total',
                'transactions.discount',
            ]);

        $buckets = [];

        foreach ($rows as $row) {
            $month = $this->monthBucket($row->date);
            if ($month === null) {
                continue;
            }

            $key = $month['key'];
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $month['label'],
                    'year' => $month['year'],
                    'month' => $month['month'],
                    'sold_qty' => 0.0,
                    'returned_qty' => 0.0,
                    'sold_value' => 0.0,
                    'returned_value' => 0.0,
                ];
            }

            $qty = abs((float) $row->quantity);
            $headerDiscount = max(0.0, min(100.0, (float) ($row->discount ?? 0)));
            $value = (float) $row->total * (100 - $headerDiscount) / 100;

            if ((int) $row->transaction_type === $sell) {
                $buckets[$key]['sold_qty'] += $qty;
                $buckets[$key]['sold_value'] += $value;
            } else {
                $buckets[$key]['returned_qty'] += $qty;
                $buckets[$key]['returned_value'] += $value;
            }
        }

        krsort($buckets);

        $months = [];
        $totals = [
            'sold_qty' => 0.0,
            'returned_qty' => 0.0,
            'net_qty' => 0.0,
            'sold_value' => 0.0,
            'returned_value' => 0.0,
            'net_value' => 0.0,
        ];

        foreach ($buckets as $bucket) {
            $netQty = max(0.0, $bucket['sold_qty'] - $bucket['returned_qty']);
            $netValue = max(0.0, $bucket['sold_value'] - $bucket['returned_value']);

            $months[] = [
                'label' => $bucket['label'],
                'year' => $bucket['year'],
                'month' => $bucket['month'],
                'sold_qty' => $bucket['sold_qty'],
                'returned_qty' => $bucket['returned_qty'],
                'net_qty' => $netQty,
                'sold_value' => $bucket['sold_value'],
                'returned_value' => $bucket['returned_value'],
                'net_value' => $netValue,
            ];

            $totals['sold_qty'] += $bucket['sold_qty'];
            $totals['returned_qty'] += $bucket['returned_qty'];
            $totals['net_qty'] += $netQty;
            $totals['sold_value'] += $bucket['sold_value'];
            $totals['returned_value'] += $bucket['returned_value'];
            $totals['net_value'] += $netValue;
        }

        return [
            'months' => $months,
            'totals' => $totals,
            'has_data' => $months !== [],
        ];
    }

    /**
     * @return array{key: string, label: string, year: int, month: int}|null
     */
    private function monthBucket(mixed $date): ?array
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return null;
        }

        try {
            $carbon = $date instanceof CarbonInterface
                ? $date
                : Carbon::parse((string) $date);
        } catch (\Throwable) {
            return null;
        }

        if ((int) $carbon->year < 1990) {
            return null;
        }

        return [
            'key' => $carbon->format('Y-m'),
            'label' => $carbon->format('F Y'),
            'year' => (int) $carbon->year,
            'month' => (int) $carbon->month,
        ];
    }
}
