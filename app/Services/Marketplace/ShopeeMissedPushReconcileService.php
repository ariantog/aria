<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceMissedPushGap;
use App\Models\Shopeesync;
use App\Services\Marketplace\Contracts\MissedPushChannelReconciler;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Detect Shopee orders visible on platform API with no marketplace_channel_orders row (missed push/webhook).
 * Ingests first-time gaps only — does not replay status updates for known orders.
 */
class ShopeeMissedPushReconcileService implements MissedPushChannelReconciler
{
    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeOrderPollService $poll,
    ) {}

    public function channel(): string
    {
        return MarketplaceWebhookRecorder::CHANNEL_SHOPEE;
    }

    public function enabled(): bool
    {
        return (bool) config('marketplace.missed_push_reconcile.enabled', false);
    }

    /**
     * @return array{
     *   shops: int,
     *   orders_seen: int,
     *   gaps_queued: int,
     *   ingested: int,
     *   skipped: int,
     *   errors: list<string>
     * }
     */
    public function reconcile(?int $onlyShopId = null, ?int $days = null, bool $ingestGaps = true): array
    {
        $summary = [
            'shops' => 0,
            'orders_seen' => 0,
            'gaps_queued' => 0,
            'ingested' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (! $this->enabled()) {
            return $summary;
        }

        if (! Schema::hasTable('shopee_syncs') || ! Schema::hasTable('marketplace_channel_orders')) {
            return $summary;
        }

        $days = $this->windowDays($days);
        $timeTo = time();
        $timeFrom = $timeTo - ($days * 86_400);
        $statuses = $this->actionableStatuses();

        $query = Shopeesync::query()
            ->where('shop_id', '>', 0)
            ->where('warehouse_id', '>', 0);

        if ($onlyShopId !== null && $onlyShopId > 0) {
            $query->where('shop_id', $onlyShopId);
        }

        $syncRows = $query->orderBy('shop_id')->get();
        $summary['shops'] = $syncRows->count();

        foreach ($syncRows as $sync) {
            $shopId = (int) $sync->shop_id;
            if ($shopId <= 0) {
                continue;
            }

            $shopRef = (string) $shopId;

            if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
                $summary['errors'][] = "Shop {$shopId}: belum diotorisasi di Shopee Admin OAuth.";
                continue;
            }

            foreach ($statuses as $orderStatus) {
                $cursor = '';
                do {
                    $listBody = $this->shopeeApi->getOrderList($shopId, [
                        'time_range_field' => 'update_time',
                        'time_from' => $timeFrom,
                        'time_to' => $timeTo,
                        'page_size' => 100,
                        'cursor' => $cursor,
                        'order_status' => $orderStatus,
                    ]);

                    if ($listBody === null) {
                        $summary['errors'][] = "Shop {$shopId}: get_order_list gagal ({$orderStatus}).";
                        break;
                    }

                    $response = $listBody['response'] ?? $listBody;
                    if (! is_array($response)) {
                        break;
                    }

                    $orderList = $response['order_list'] ?? [];
                    if (! is_array($orderList)) {
                        $orderList = [];
                    }

                    foreach ($orderList as $row) {
                        if (! is_array($row)) {
                            continue;
                        }

                        $orderSn = (string) ($row['order_sn'] ?? $row['ordersn'] ?? '');
                        if ($orderSn === '') {
                            continue;
                        }

                        $summary['orders_seen']++;

                        if ($this->orderExistsInAria($orderSn)) {
                            $this->closeGapIfOpen($orderSn, $shopRef);
                            $summary['skipped']++;

                            continue;
                        }

                        $status = (string) ($row['order_status'] ?? $orderStatus);
                        $updateTime = isset($row['update_time']) ? (int) $row['update_time'] : null;
                        $platformUpdateAt = $updateTime !== null && $updateTime > 0
                            ? Carbon::createFromTimestamp($updateTime, config('app.timezone'))
                            : null;

                        if ($this->queueGap($shopRef, $orderSn, $status, $platformUpdateAt)) {
                            $summary['gaps_queued']++;
                        }

                        if ($ingestGaps) {
                            $ingest = $this->poll->pullAndIngestOrder(
                                $shopId,
                                $orderSn,
                                $status,
                                $updateTime ?? $timeTo,
                            );

                            if (($ingest['error'] ?? null) !== null) {
                                $summary['errors'][] = "Shop {$shopId} order {$orderSn}: ".$ingest['error'];
                                continue;
                            }

                            if ($ingest['ingested'] && $this->orderExistsInAria($orderSn)) {
                                $this->markGapIngested($orderSn);
                                $summary['ingested']++;
                            }
                        }
                    }

                    $more = (bool) ($response['more'] ?? false);
                    $cursor = (string) ($response['next_cursor'] ?? '');
                    if (! $more || $cursor === '') {
                        break;
                    }
                } while (true);
            }

            if (Schema::hasColumn('shopee_syncs', 'last_order_poll_at')) {
                Shopeesync::query()
                    ->where('id', $sync->id)
                    ->update(['last_order_poll_at' => now()]);
            }
        }

        if ($summary['errors'] !== []) {
            Log::warning('Shopee missed-push reconcile completed with errors', $summary);
        }

        return $summary;
    }

    public function ingestGap(MarketplaceMissedPushGap $gap): array
    {
        $shopId = (int) $gap->shop_ref;
        $orderSn = (string) $gap->order_ref;

        return $this->poll->pullAndIngestOrder(
            $shopId,
            $orderSn,
            $gap->order_status,
            $gap->platform_update_at?->getTimestamp(),
        );
    }

    private function orderExistsInAria(string $orderRef): bool
    {
        return MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderRef)
            ->exists();
    }

    private function queueGap(
        string $shopRef,
        string $orderRef,
        string $status,
        ?Carbon $platformUpdateAt,
    ): bool {
        if (! Schema::hasTable('marketplace_missed_push_gaps')) {
            return false;
        }

        $gap = MarketplaceMissedPushGap::query()->firstOrNew([
            'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
            'order_ref' => $orderRef,
        ]);

        if ($gap->exists && $gap->ingested_at !== null) {
            return false;
        }

        $wasNew = ! $gap->exists;

        if ($wasNew) {
            $gap->detected_at = now();
        }

        $gap->shop_ref = $shopRef;
        $gap->order_status = $status;
        $gap->platform_update_at = $platformUpdateAt;
        $gap->save();

        return $wasNew;
    }

    private function markGapIngested(string $orderRef): void
    {
        if (! Schema::hasTable('marketplace_missed_push_gaps')) {
            return;
        }

        MarketplaceMissedPushGap::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderRef)
            ->whereNull('ingested_at')
            ->update(['ingested_at' => now()]);
    }

    private function closeGapIfOpen(string $orderRef, string $shopRef): void
    {
        if (! Schema::hasTable('marketplace_missed_push_gaps')) {
            return;
        }

        MarketplaceMissedPushGap::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderRef)
            ->whereNull('ingested_at')
            ->update([
                'ingested_at' => now(),
                'shop_ref' => $shopRef,
            ]);
    }

    private function windowDays(?int $days): int
    {
        $max = max(1, (int) config('marketplace.missed_push_reconcile.max_days', 30));
        $default = max(1, (int) config('marketplace.missed_push_reconcile.days', 14));

        if ($days !== null && $days > 0) {
            return min($days, $max);
        }

        return min($default, $max);
    }

    /**
     * @return list<string>
     */
    private function actionableStatuses(): array
    {
        $configured = config('marketplace.missed_push_reconcile.shopee.order_statuses', []);

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return [
            'UNPAID',
            'READY_TO_SHIP',
            'PROCESSED',
            'SHIPPED',
        ];
    }
}