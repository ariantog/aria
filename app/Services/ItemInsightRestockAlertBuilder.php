<?php

namespace App\Services;

use App\Models\Addrbook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemInsightRestockAlertBuilder
{
    public const LOW_COVER_DAYS = 14;

    public const HIGH_SOLD_RATIO = 1.0;

    public const MIN_NET_QTY = 1.0;

    public function __construct(
        private readonly ItemInsightLastBuyResolver $lastBuys,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    public function rank(array $aggregates, int $daysInPeriod): array
    {
        if ($aggregates === []) {
            return [];
        }

        $itemIds = array_map(fn (array $row) => (int) $row['item_id'], $aggregates);
        $stockByItem = $this->physicalStockByItem($itemIds);
        $buyByItem = $this->lastBuys->forItems($itemIds);
        $daysInPeriod = max(1, $daysInPeriod);
        $today = now()->startOfDay();

        $candidates = [];
        foreach ($aggregates as $row) {
            $itemId = (int) $row['item_id'];
            $netQty = (float) $row['net_qty'];
            if ($netQty < self::MIN_NET_QTY) {
                continue;
            }

            $stock = max(0.0, (float) ($stockByItem[$itemId] ?? 0));
            $velocity = $netQty / $daysInPeriod;
            if ($velocity <= 0) {
                continue;
            }

            $daysOfCover = $stock > 0 ? round($stock / $velocity, 2) : 0.0;
            $soldRatio = round($netQty / max($stock, 1.0), 4);

            $lastBuy = $buyByItem[$itemId] ?? null;
            $lastBuyQty = $lastBuy ? (float) $lastBuy['qty'] : null;
            $lastBuyDate = $lastBuy ? $lastBuy['date'] : null;
            $buyCoverDays = ($lastBuyQty !== null && $lastBuyQty > 0)
                ? round($lastBuyQty / $velocity, 2)
                : null;

            $daysSinceBuy = $lastBuyDate
                ? max(0, Carbon::parse($lastBuyDate)->startOfDay()->diffInDays($today))
                : null;

            $alertDetail = $this->buildAlertDetail(
                $daysOfCover,
                $soldRatio,
                $velocity,
                $lastBuyQty,
                $lastBuyDate,
                $buyCoverDays,
                $daysSinceBuy,
            );

            if ($alertDetail === null) {
                continue;
            }

            $candidates[] = [
                'item_id' => $itemId,
                'item_name' => $row['item_name'],
                'item_code' => $row['item_code'],
                'net_qty' => $netQty,
                'net_value' => (float) $row['net_value'],
                'cost_total' => (float) $row['cost_total'],
                'profit' => (float) $row['profit'],
                'margin_pct' => $row['margin_pct'],
                'daily_velocity' => round($velocity, 4),
                'stock_qty' => $stock,
                'days_of_cover' => $daysOfCover,
                'sold_ratio' => $soldRatio,
                'last_buy_qty' => $lastBuyQty,
                'last_buy_date' => $lastBuyDate,
                'buy_cover_days' => $buyCoverDays,
                'alert_detail' => $alertDetail,
            ];
        }

        usort($candidates, function (array $a, array $b): int {
            $coverA = $a['days_of_cover'] ?? 9999;
            $coverB = $b['days_of_cover'] ?? 9999;

            return [$coverA, -$a['sold_ratio'], -$a['daily_velocity'], $a['item_id']]
                <=> [$coverB, -$b['sold_ratio'], -$b['daily_velocity'], $b['item_id']];
        });

        return array_slice($candidates, 0, ItemInsightSyncService::TOP_LIMIT);
    }

    private function buildAlertDetail(
        float $daysOfCover,
        float $soldRatio,
        float $velocity,
        ?float $lastBuyQty,
        ?string $lastBuyDate,
        ?float $buyCoverDays,
        ?int $daysSinceBuy,
    ): ?string {
        $parts = [];

        $lowCover = $daysOfCover < self::LOW_COVER_DAYS;
        $hotSeller = $soldRatio >= self::HIGH_SOLD_RATIO;

        if ($lowCover && ($hotSeller || $daysOfCover <= 0)) {
            $parts[] = sprintf(
                '≈%s days of stock at current sell rate (sold ratio %s×)',
                number_format($daysOfCover, 1),
                number_format($soldRatio, 1),
            );
        } elseif ($lowCover) {
            $parts[] = sprintf('Low cover: ≈%s days left', number_format($daysOfCover, 1));
        } elseif ($hotSeller) {
            $parts[] = sprintf('High sold ratio %s× vs on-hand stock', number_format($soldRatio, 1));
        }

        if ($buyCoverDays !== null && $daysSinceBuy !== null && $daysSinceBuy > $buyCoverDays) {
            $parts[] = sprintf(
                'Selling faster than last buy (%s units on %s ≈ %s days supply, %s days ago)',
                number_format($lastBuyQty ?? 0, 0),
                $lastBuyDate,
                number_format($buyCoverDays, 1),
                number_format($daysSinceBuy, 0),
            );
        } elseif ($buyCoverDays !== null && $daysOfCover < $buyCoverDays * 0.5) {
            $parts[] = sprintf(
                'Stock will run out before last buy coverage (%s days vs %s days at sell rate)',
                number_format($daysOfCover, 1),
                number_format($buyCoverDays, 1),
            );
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    private function physicalStockByItem(array $itemIds): array
    {
        return DB::table('warehouse_item as wi')
            ->join('customers as wh', 'wh.id', '=', 'wi.warehouse_id')
            ->whereNull('wh.deleted_at')
            ->where('wh.type', Addrbook::TYPE_WAREHOUSE)
            ->whereIn('wi.item_id', $itemIds)
            ->groupBy('wi.item_id')
            ->selectRaw('wi.item_id as item_id, COALESCE(SUM(wi.quantity), 0) as stock_qty')
            ->pluck('stock_qty', 'item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }
}
