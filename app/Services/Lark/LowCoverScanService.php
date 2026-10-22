<?php

namespace App\Services\Lark;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemInsightRanking;
use App\Models\OperationalAlert;
use App\Services\Restock\RestockRecommendationApplyService;
use Illuminate\Support\Facades\DB;

class LowCoverScanService
{
    public function __construct(
        protected OperationalAlertDispatcher $alerts,
        protected LarkRestockNotificationService $restockNotifier,
    ) {}

    public function run(): int
    {
        if (! config('lark.enabled')) {
            return 0;
        }

        $lowCover = app(LarkSettingsService::class)->lowCover();
        $defaultDays = (int) $lowCover['default_cover_days'];
        $bestDays = (int) $lowCover['best_seller_cover_days'];
        $buffer = (float) $lowCover['rearm_buffer_days'];
        $mismatchDelta = (int) $lowCover['mismatch_qty_delta'];

        $bestSellerIds = $this->bestSellerItemIds();
        $candidates = $this->loadCoverCandidates();

        $sent = 0;
        foreach ($candidates as $row) {
            $itemId = (int) $row['item_id'];
            $isBest = isset($bestSellerIds[$itemId]);
            $threshold = $isBest ? $bestDays : $defaultDays;

            $ariaCover = $row['cover_aria'];
            $jubCover = $row['cover_jubelio'];
            $ariaQty = $row['aria_qty'];
            $jubAvail = $row['jubelio_available'];

            $ariaLow = $ariaCover !== null && $ariaCover < $threshold;
            $jubLow = $jubCover !== null && $jubCover < $threshold;

            if (! $ariaLow && ! $jubLow) {
                $minCover = min(array_filter([$ariaCover, $jubCover], fn ($v) => $v !== null) ?: [9999]);
                $openFp = OperationalAlert::query()
                    ->where('fingerprint', 'like', 'low_cover:'.$itemId.':%')
                    ->where('status', 'open')
                    ->whereNotNull('sent_at')
                    ->first();
                if ($openFp !== null && $minCover >= ($threshold + $buffer)) {
                    $openFp->status = 'rearmed';
                    $openFp->rearmed_at = now();
                    $openFp->save();
                }

                continue;
            }

            $mismatch = $jubAvail !== null && abs($ariaQty - $jubAvail) >= $mismatchDelta;
            $bothLow = $ariaLow && $jubLow;
            $eventKey = $bothLow ? 'stock.low_cover.both' : 'stock.low_cover';

            $band = (int) floor(min(
                $ariaCover ?? 999,
                $jubCover ?? 999,
            ));
            $fingerprint = sprintf('low_cover:%d:%d:%d', $itemId, $threshold, $band);

            $existing = OperationalAlert::query()->where('fingerprint', $fingerprint)->first();
            if ($existing !== null && $existing->sent_at !== null && $existing->status !== 'rearmed') {
                continue;
            }

            $item = Item::query()->find($itemId);
            if ($item === null) {
                continue;
            }

            $larkSettings = app(LarkSettingsService::class);
            if (! $larkSettings->itemTypeEligibleForLowCoverAlert($item)) {
                continue;
            }

            if (! $larkSettings->eventSendEnabled($eventKey)) {
                continue;
            }

            $monthlyNet = (float) ($row['net_qty_30d'] ?? 0);
            $suggested = RestockRecommendationApplyService::suggestedQtyFromMonthlyRate($monthlyNet);

            $this->alerts->dispatch(
                $eventKey,
                $fingerprint,
                $bothLow ? 'warning' : 'info',
                'Stok rendah (cover)',
                [
                    'SKU' => $item->code,
                    'Nama' => $item->name,
                    'Cover Aria (hari)' => $ariaCover !== null ? round($ariaCover, 1) : '—',
                    'Cover Jubelio (hari)' => $jubCover !== null ? round($jubCover, 1) : '—',
                    'Qty Aria' => $ariaQty,
                    'Jubelio avail' => $jubAvail ?? '—',
                    'Selisih qty' => $jubAvail !== null ? round($ariaQty - $jubAvail, 2) : '—',
                    'Mismatch' => $mismatch ? 'ya' : 'tidak',
                ],
            );

            if ($mismatch && ! $bothLow && $larkSettings->eventSendEnabled('stock.mismatch')) {
                $this->alerts->dispatch(
                    'stock.mismatch',
                    'mismatch:'.$itemId.':'.$band,
                    'warning',
                    'Selisih stok Aria vs Jubelio',
                    [
                        'SKU' => $item->code,
                        'Qty Aria' => $ariaQty,
                        'Jubelio avail' => $jubAvail,
                    ],
                );
            }

            if (ItemType::coerce($item->type) === ItemType::ASSET_LANCAR) {
                $this->restockNotifier->sendRestockApprovalCard($item, $suggested, [
                    'code' => $item->code,
                    'name' => $item->name,
                    'aria_qty' => $ariaQty,
                    'jubelio_available' => $jubAvail,
                    'cover_days_aria' => $ariaCover !== null ? round($ariaCover, 1) : null,
                    'cover_days_jubelio' => $jubCover !== null ? round($jubCover, 1) : null,
                    'net_sell_30d' => round((float) ($row['net_qty_30d'] ?? 0), 2),
                    'item_url' => $item->showUrl(),
                ]);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * @return array<int, true>
     */
    protected function bestSellerItemIds(): array
    {
        $period = DB::table('item_insight_months')
            ->whereNotNull('calculated_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if ($period === null) {
            return [];
        }

        return ItemInsightRanking::query()
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('category', ItemInsightRanking::CATEGORY_BEST_SELLING)
            ->orderBy('rank')
            ->limit(50)
            ->pluck('item_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadCoverCandidates(): array
    {
        $since = now()->subDays(30)->toDateString();

        $sales = DB::table('transaction_details')
            ->select('item_id', DB::raw('SUM(ABS(quantity)) as net_qty'))
            ->where('transaction_type', 2)
            ->where('date', '>=', $since)
            ->groupBy('item_id')
            ->having('net_qty', '>', 0)
            ->get();

        if ($sales->isEmpty()) {
            return [];
        }

        $itemIds = $sales->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $stockByItem = DB::table('warehouse_item')
            ->join('customers as w', 'w.id', '=', 'warehouse_item.warehouse_id')
            ->where('w.type', 2)
            ->whereNull('w.deleted_at')
            ->whereIn('warehouse_item.item_id', $itemIds)
            ->groupBy('warehouse_item.item_id')
            ->select('warehouse_item.item_id', DB::raw('SUM(warehouse_item.quantity) as qty'))
            ->pluck('qty', 'item_id');

        $rows = [];
        foreach ($sales as $sale) {
            $itemId = (int) $sale->item_id;
            $net = (float) $sale->net_qty;
            $daily = $net / 30;
            if ($daily <= 0) {
                continue;
            }

            $ariaQty = (float) ($stockByItem[$itemId] ?? 0);
            $coverAria = $ariaQty / $daily;

            $jubAvail = null;
            $coverJub = null;
            $item = Item::query()->find($itemId);
            if ($item !== null && (int) $item->jubelio_item_id > 0) {
                $jubAvail = $this->fetchJubelioAvailableTotal($item);
                if ($jubAvail !== null) {
                    $coverJub = $jubAvail / $daily;
                }
            }

            $rows[] = [
                'item_id' => $itemId,
                'net_qty_30d' => $net,
                'aria_qty' => $ariaQty,
                'cover_aria' => $coverAria,
                'jubelio_available' => $jubAvail,
                'cover_jubelio' => $coverJub,
            ];
        }

        return $rows;
    }

    protected function fetchJubelioAvailableTotal(Item $item): ?float
    {
        try {
            $service = app(\App\Services\Items\ItemJubelioStockService::class);
            $warehouseItems = $item->warehouseItems()->with('warehouse')->get();
            $detail = $service->detailStockForItem($item, $warehouseItems);
            $available = $detail['total']['available'] ?? null;

            return $available !== null ? (float) $available : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
