<?php

namespace App\Services\Readonly;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadonlyLastBuyResolver
{
    /**
     * Latest completed BUY line per item: date and unit price (avg when multiple lines share the date).
     *
     * @param  list<int>  $itemIds
     * @return array<int, array{date: string, unit_price: float, qty: float}>
     */
    public function forItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $buyType = Transaction::TYPE_BUY;
        $minDate = '1970-01-01';
        $asOfDate = Carbon::now()->toDateString();

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
            ->selectRaw('td.item_id as item_id, td.date as last_buy_date, SUM(ABS(td.quantity)) as last_buy_qty, AVG(ABS(td.price)) as last_buy_unit_price')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $map[$itemId] = [
                'qty' => (float) $row->last_buy_qty,
                'date' => Carbon::parse($row->last_buy_date)->toDateString(),
                'unit_price' => round((float) $row->last_buy_unit_price, 2),
            ];
        }

        return $map;
    }
}
