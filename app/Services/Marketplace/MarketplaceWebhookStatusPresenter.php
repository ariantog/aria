<?php

namespace App\Services\Marketplace;

/**
 * Normalizes raw marketplace webhook statuses for storage display and coarse ops buckets.
 *
 * Shopee push code 4 (order_trackingno_push) has no data.status; ingest stores
 * {@see self::TRACKING_PREFIX}{tracking_no} (e.g. tracking:SPX123456).
 */
class MarketplaceWebhookStatusPresenter
{
    public const TRACKING_PREFIX = 'tracking:';

    public const FULFILLMENT_PREFIX = 'FULFILLMENT:';

    public const CHANNEL_SHOPEE = MarketplaceWebhookRecorder::CHANNEL_SHOPEE;

    public const CHANNEL_TIKTOK = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;

    /** @var array<string, array<string, int>> */
    private const SUMMARY_STATUS_RANK = [
        'shopee' => [
            'UNPAID' => 10,
            'READY_TO_SHIP' => 50,
            'PROCESSED' => 50,
            'RETRY_SHIP' => 50,
            'SHIPPED' => 70,
            'TO_CONFIRM_RECEIVE' => 70,
            'COMPLETED' => 90,
            'IN_CANCEL' => 100,
            'CANCELLED' => 100,
            'CANCELED' => 100,
        ],
        'tiktok' => [
            'UNPAID' => 10,
            'ON_HOLD' => 10,
            'AWAITING_SHIPMENT' => 50,
            'AWAITING_COLLECTION' => 50,
            'PARTIALLY_SHIPPING' => 70,
            'IN_TRANSIT' => 70,
            'DELIVERED' => 70,
            'COMPLETED' => 90,
            'CANCEL' => 100,
            'CANCELLED' => 100,
            'CANCELED' => 100,
        ],
        'tokopedia' => [
            'UNPAID' => 10,
            'ON_HOLD' => 10,
            'AWAITING_SHIPMENT' => 50,
            'AWAITING_COLLECTION' => 50,
            'PARTIALLY_SHIPPING' => 70,
            'IN_TRANSIT' => 70,
            'DELIVERED' => 70,
            'COMPLETED' => 90,
            'CANCEL' => 100,
            'CANCELLED' => 100,
            'CANCELED' => 100,
        ],
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveIngestStatus(string $channel, int|string|null $eventType, array $payload): ?string
    {
        $channel = strtolower($channel);

        if ($channel === self::CHANNEL_SHOPEE) {
            $code = is_numeric($eventType) ? (int) $eventType : (int) ($eventType ?? 0);

            if ($code === 4) {
                return $this->resolveShopeeTrackingIngestStatus($payload);
            }

            if ($code === 30) {
                return $this->resolveShopeeFulfillmentIngestStatus($payload);
            }

            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            if (isset($data['status']) && $data['status'] !== '') {
                return (string) $data['status'];
            }

            return null;
        }

        if ($channel === self::CHANNEL_TIKTOK) {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            if (isset($data['order_status']) && $data['order_status'] !== '') {
                return (string) $data['order_status'];
            }

            return null;
        }

        return null;
    }

    public function eventTypeLabel(string $channel, int|string|null $eventType): string
    {
        $channel = strtolower($channel);
        $type = $eventType !== null && $eventType !== '' ? (string) $eventType : '';

        if ($channel === self::CHANNEL_SHOPEE) {
            return match ($type) {
                '3' => 'Order status',
                '4' => 'Tracking',
                '30' => 'Package fulfillment',
                '0' => 'Verify',
                default => $type !== '' ? 'Push '.$type : '—',
            };
        }

        if ($channel === self::CHANNEL_TIKTOK) {
            return match ($type) {
                '1' => 'Order status',
                default => $type !== '' ? 'Type '.$type : '—',
            };
        }

        return $type !== '' ? $type : '—';
    }

    public function normalizeBucket(
        string $channel,
        ?string $commercePlatform,
        ?string $rawStatus,
    ): ?string {
        if ($rawStatus === null || $rawStatus === '') {
            return null;
        }

        if ($this->isTrackingIngestStatus($rawStatus)) {
            return 'tracking_update';
        }

        if ($this->isFulfillmentIngestStatus($rawStatus)) {
            return 'fulfillment_update';
        }

        $channel = strtolower($channel);
        $key = $channel;
        if ($channel === self::CHANNEL_TIKTOK
            && strtoupper((string) $commercePlatform) === 'TOKOPEDIA') {
            $key = 'tokopedia';
        }

        $map = config("marketplace.webhook_status_buckets.{$key}", []);
        $upper = strtoupper($rawStatus);

        return $map[$upper] ?? $map[$rawStatus] ?? 'other';
    }

    public function displayStatus(string $channel, ?string $rawStatus): string
    {
        if ($rawStatus === null || $rawStatus === '') {
            return '—';
        }

        if ($this->isTrackingIngestStatus($rawStatus)) {
            $no = $this->trackingNumberFromIngestStatus($rawStatus);

            return 'TRACKING · '.$no;
        }

        if ($this->isFulfillmentIngestStatus($rawStatus)) {
            $segment = substr($rawStatus, strlen(self::FULFILLMENT_PREFIX));

            return 'FULFILLMENT · '.str_replace('_', ' ', $segment);
        }

        return str_replace('_', ' ', $rawStatus);
    }

    public function bucketChipLabel(?string $bucket): ?string
    {
        if ($bucket === null || $bucket === '') {
            return null;
        }

        return match ($bucket) {
            'unpaid' => 'Unpaid',
            'awaiting_shipment' => 'Awaiting shipment',
            'in_transit' => 'In transit',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'tracking_update' => 'Tracking',
            'fulfillment_update' => 'Fulfillment',
            'other' => 'Other',
            default => str_replace('_', ' ', $bucket),
        };
    }

    public function isTrackingIngestStatus(?string $rawStatus): bool
    {
        return is_string($rawStatus)
            && str_starts_with($rawStatus, self::TRACKING_PREFIX)
            && strlen($rawStatus) > strlen(self::TRACKING_PREFIX);
    }

    public function skipsChannelOrderStatusUpsert(?string $rawStatus): bool
    {
        return $this->isTrackingIngestStatus($rawStatus)
            || $this->isFulfillmentIngestStatus($rawStatus);
    }

    public function isFulfillmentIngestStatus(?string $rawStatus): bool
    {
        return is_string($rawStatus)
            && str_starts_with($rawStatus, self::FULFILLMENT_PREFIX)
            && strlen($rawStatus) > strlen(self::FULFILLMENT_PREFIX);
    }

    public function shouldReplaceChannelOrderStatus(
        string $channel,
        ?string $current,
        ?string $incoming,
    ): bool {
        if ($incoming === null || trim($incoming) === '') {
            return false;
        }

        if ($this->skipsChannelOrderStatusUpsert($incoming)) {
            return false;
        }

        $channel = strtolower($channel);
        $incomingKey = strtoupper(trim($incoming));

        if ($this->isTerminalCancelledStatus($channel, $incomingKey)) {
            return true;
        }

        if ($current !== null && trim($current) !== ''
            && $this->isTerminalCancelledStatus($channel, strtoupper(trim($current)))) {
            return false;
        }

        $currentRank = $this->summaryStatusRank($channel, $current);
        $incomingRank = $this->summaryStatusRank($channel, $incoming);

        if ($currentRank === null) {
            return true;
        }

        if ($incomingRank === null) {
            return true;
        }

        return $incomingRank >= $currentRank;
    }

    public function shouldPromoteShopeeStatusAfterAwb(?string $currentStatus): bool
    {
        return $this->shouldPromoteFulfillmentStatusAfterAwb(self::CHANNEL_SHOPEE, $currentStatus);
    }

    public function shouldPromoteTikTokStatusAfterAwb(?string $currentStatus): bool
    {
        return $this->shouldPromoteFulfillmentStatusAfterAwb(self::CHANNEL_TIKTOK, $currentStatus);
    }

    private function shouldPromoteFulfillmentStatusAfterAwb(string $channel, ?string $currentStatus): bool
    {
        if ($currentStatus === null || trim($currentStatus) === '') {
            return true;
        }

        if ($this->skipsChannelOrderStatusUpsert($currentStatus)) {
            return false;
        }

        $rank = $this->summaryStatusRank($channel, $currentStatus);

        if ($rank === null) {
            return true;
        }

        if ($this->isTerminalCancelledStatus($channel, strtoupper(trim($currentStatus)))) {
            return false;
        }

        return $rank <= 50;
    }

    /**
     * Webhook payload may still say UNPAID while marketplace allows fulfillment (COD).
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveSummaryStatusForChannelOrder(
        string $channel,
        ?string $ingestStatus,
        array $payload = [],
    ): ?string {
        if ($ingestStatus === null || trim($ingestStatus) === '') {
            return null;
        }

        if ($this->skipsChannelOrderStatusUpsert($ingestStatus)) {
            return null;
        }

        if (! $this->isCodWebhookPayload($channel, $payload)) {
            return $ingestStatus;
        }

        $upper = strtoupper(trim($ingestStatus));
        $channel = strtolower($channel);

        if ($channel === self::CHANNEL_SHOPEE && $upper === 'UNPAID') {
            return 'PROCESSED';
        }

        if ($channel === self::CHANNEL_TIKTOK && in_array($upper, ['UNPAID', 'ON_HOLD'], true)) {
            return 'AWAITING_SHIPMENT';
        }

        return $ingestStatus;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isCodWebhookPayload(string $channel, array $payload): bool
    {
        $channel = strtolower($channel);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates = [];

        foreach (['payment_method', 'payment_method_name', 'method_pay', 'pay_method'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $candidates[] = (string) $data[$key];
            }
        }

        if (isset($data['cod']) && $data['cod']) {
            return true;
        }

        if (isset($data['is_cod']) && $data['is_cod']) {
            return true;
        }

        foreach ($candidates as $value) {
            $normalized = strtoupper(trim($value));
            if ($normalized === '' || $normalized === '0') {
                continue;
            }

            if (str_contains($normalized, 'COD')
                || str_contains($normalized, 'CASH ON DELIVERY')
                || str_contains($normalized, 'BAYAR DI TEMPAT')) {
                return true;
            }

            if ($channel === self::CHANNEL_TIKTOK && $normalized === '5') {
                return true;
            }
        }

        return false;
    }

    public function summaryStatusRank(string $channel, ?string $rawStatus): ?int
    {
        if ($rawStatus === null || trim($rawStatus) === '') {
            return null;
        }

        if ($this->skipsChannelOrderStatusUpsert($rawStatus)) {
            return null;
        }

        $channel = strtolower($channel);
        $upper = strtoupper(trim($rawStatus));
        $maps = self::SUMMARY_STATUS_RANK;

        if ($channel === self::CHANNEL_TIKTOK) {
            return $maps['tiktok'][$upper] ?? null;
        }

        return $maps[$channel][$upper] ?? null;
    }

    public function isTerminalCancelledStatus(string $channel, string $upperStatus): bool
    {
        $bucket = $this->normalizeBucket($channel, null, $upperStatus);

        return $bucket === 'cancelled';
    }

    /**
     * Shopee push code 30 — package_fulfillment_status_push (logistics_status on data).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveShopeeFulfillmentIngestStatus(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        foreach (['logistics_status', 'fulfillment_status', 'package_fulfillment_status', 'status'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $value = strtoupper(trim((string) $data[$key]));

                return str_starts_with($value, 'FULFILLMENT:')
                    ? $value
                    : 'FULFILLMENT:'.$value;
            }
        }

        return null;
    }

    protected function resolveShopeeTrackingIngestStatus(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $tracking = isset($data['tracking_no']) ? trim((string) $data['tracking_no']) : '';
        if ($tracking === '' && isset($data['tracking_number'])) {
            $tracking = trim((string) $data['tracking_number']);
        }

        if ($tracking === '') {
            return null;
        }

        return self::TRACKING_PREFIX.$tracking;
    }

    protected function trackingNumberFromIngestStatus(string $rawStatus): string
    {
        return substr($rawStatus, strlen(self::TRACKING_PREFIX));
    }
}