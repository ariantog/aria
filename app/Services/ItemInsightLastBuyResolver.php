<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemInsightLastBuyResolver
{
    /**
     * Most recent buy line per item on or before {@see $asOf} (defaults to today).
     * Sums qty when multiple lines share that buy date.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array{qty: float, date: string}>
     */
    public function forItems(array $itemIds, ?\DateTimeInterface $asOf = null): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $buyType = Transaction::TYPE_BUY;
        $minDate = '1970-01-01';
        $asOfDate = Carbon::parse($asOf ?? now())->toDateString();

        $latestDates = DB::table('transaction_details')
            ->where('transaction_type', $buyType)
            ->whereIn('item_id', $itemIds)
            ->where('date', '>', $minDate)
            ->where('date', '<=', $asOfDate)
            ->groupBy('item_id')
            ->selectRaw('item_id, MAX(date) as last_buy_date');

        $rows = DB::table('transaction_details as td')
            ->joinSub($latestDates, 'lb', function ($join): void {
                $join->on('td.item_id', '=', 'lb.item_id')
                    ->on('td.date', '=', 'lb.last_buy_date');
            })
            ->where('td.transaction_type', $buyType)
            ->where('td.date', '<=', $asOfDate)
            ->groupBy('td.item_id', 'td.date')
            ->selectRaw('td.item_id as item_id, td.date as last_buy_date, SUM(ABS(td.quantity)) as last_buy_qty')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $map[$itemId] = [
                'qty' => (float) $row->last_buy_qty,
                'date' => Carbon::parse($row->last_buy_date)->toDateString(),
            ];
        }

        return $map;
    }
}
