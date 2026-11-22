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

    public const CHANNEL_SHOPEE = MarketplaceWebhookRecorder::CHANNEL_SHOPEE;

    public const CHANNEL_TIKTOK = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;

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
        return $this->isTrackingIngestStatus($rawStatus);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
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