<?php

namespace App\Services\Marketplace;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace "when the customer ordered" timestamp for list filters — not Aria webhook receipt.
 *
 * Shopee: pay_time when prepaid (API/enriched payload); COD uses create_time; else YYMMDD from order_sn.
 * TikTok: paid_time when prepaid; COD uses create_time; webhook fallback update_time on first ingest.
 */
class MarketplaceChannelOrderOrderedAtResolver
{
    public function __construct(
        protected MarketplaceWebhookStatusPresenter $statusPresenter,
    ) {}

    public static function ordersDateColumn(): string
    {
        return Schema::hasColumn('marketplace_channel_orders', 'marketplace_ordered_at')
            ? 'marketplace_ordered_at'
            : 'last_event_at';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveFromWebhookPayload(
        string $channel,
        string $orderRef,
        array $payload,
        ?string $orderStatus = null,
    ): ?CarbonInterface {
        $channel = strtolower(trim($channel));
        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            return $this->resolveShopee($orderRef, $payload);
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            return $this->resolveTikTok($channel, $payload, $orderStatus);
        }

        return null;
    }

    public function applyToOrder(
        \App\Models\MarketplaceChannelOrder $order,
        ?CarbonInterface $candidate,
    ): void {
        if ($candidate === null) {
            return;
        }

        $existing = $order->marketplace_ordered_at;
        if ($existing === null) {
            $order->marketplace_ordered_at = $candidate;

            return;
        }

        if ($candidate->lt($existing)) {
            $order->marketplace_ordered_at = $candidate;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveShopee(string $orderRef, array $payload): ?CarbonInterface
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $isCod = $this->statusPresenter->isCodWebhookPayload(MarketplaceWebhookRecorder::CHANNEL_SHOPEE, $payload);

        $createTime = $this->unixToCarbon($data['create_time'] ?? null);
        $payTime = $this->unixToCarbon($data['pay_time'] ?? null);
        $snDate = $this->parseShopeeOrderSnDate($orderRef);

        if ($isCod) {
            return $createTime ?? $snDate;
        }

        return $payTime ?? $createTime ?? $snDate;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTikTok(string $channel, array $payload, ?string $orderStatus): ?CarbonInterface
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $isCod = $this->statusPresenter->isCodWebhookPayload($channel, $payload);

        $createTime = $this->unixToCarbon(
            $data['create_time'] ?? $data['order_create_time'] ?? null,
        );
        $paidTime = $this->unixToCarbon(
            $data['paid_time'] ?? $data['pay_time'] ?? $data['payment_time'] ?? null,
        );
        $updateTime = $this->unixToCarbon($data['update_time'] ?? null);
        $webhookTs = $this->unixToCarbon($payload['timestamp'] ?? null);

        if ($isCod) {
            return $createTime ?? $updateTime ?? $webhookTs;
        }

        if ($paidTime !== null) {
            return $paidTime;
        }

        if ($createTime !== null) {
            return $createTime;
        }

        if ($updateTime !== null && $this->isUnpaidStatus($orderStatus)) {
            return $updateTime;
        }

        return $updateTime ?? $webhookTs;
    }

    protected function isUnpaidStatus(?string $orderStatus): bool
    {
        if ($orderStatus === null || trim($orderStatus) === '') {
            return false;
        }

        return strtoupper(trim($orderStatus)) === 'UNPAID';
    }

    public function parseShopeeOrderSnDate(string $orderRef): ?CarbonInterface
    {
        $orderRef = trim($orderRef);
        if ($orderRef === '' || ! preg_match('/^(\d{2})(\d{2})(\d{2})/', $orderRef, $matches)) {
            return null;
        }

        $year = 2000 + (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day, 0, 0, 0, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function unixToCarbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $seconds = (int) $value;
        if ($seconds <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp($seconds, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}