<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOnOrderReservation;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWebhookEvent;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve sell lines from shadow storage / on_order reservations (no new marketplace API).
 */
class OmnichannelPostSellLineResolver
{
    public function __construct(
        private ShopeeOrderLineSummary $shopeeLineSummary,
        private TikTokOrderLineSummary $tikTokLineSummary,
    ) {}

    /**
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    public function linesForOrder(MarketplaceChannelOrder $order): array
    {
        $channel = strtolower((string) $order->channel);
        $orderRef = trim((string) $order->order_ref);

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            return $this->linesForShopee($orderRef);
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            return $this->linesForTikTok($order);
        }

        return [];
    }

    /**
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    private function linesForShopee(string $orderRef): array
    {
        if (Schema::hasTable('marketplace_order_shadows')) {
            $shadow = MarketplaceOrderShadow::query()
                ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
                ->where('external_order_sn', $orderRef)
                ->first();

            if ($shadow !== null) {
                $fromSummary = $this->linesFromShadowSummary($shadow);
                if ($fromSummary !== []) {
                    return $fromSummary;
                }

                if ($shadow->detailLoaded()) {
                    $orderRow = $shadow->channel_payload['response']['order_list'][0] ?? null;
                    $parsed = $this->shopeeLineSummary->fromOrderRow(is_array($orderRow) ? $orderRow : null);

                    return $this->normalizeShopeeLines($parsed['lines'] ?? []);
                }
            }
        }

        return $this->linesFromReservations(MarketplaceWebhookRecorder::CHANNEL_SHOPEE, $orderRef);
    }

    /**
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    private function linesForTikTok(MarketplaceChannelOrder $order): array
    {
        if (Schema::hasTable('marketplace_webhook_events')) {
            $event = MarketplaceWebhookEvent::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where('order_ref', $order->order_ref)
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->first();

            if ($event !== null && is_array($event->payload)) {
                $skuLines = $this->tikTokLineSummary->fromWebhookPayload($event->payload);
                if ($skuLines !== []) {
                    return array_map(static fn (array $row) => [
                        'sku' => $row['sku'],
                        'qty' => $row['qty'],
                        'amount' => null,
                    ], $skuLines);
                }
            }
        }

        return $this->linesFromReservations(MarketplaceWebhookRecorder::CHANNEL_TIKTOK, $order->order_ref);
    }

    /**
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    private function linesFromShadowSummary(MarketplaceOrderShadow $shadow): array
    {
        $summary = $shadow->line_summary;
        if (! is_array($summary)) {
            return [];
        }

        $lines = $summary['lines'] ?? $summary;
        if (! is_array($lines)) {
            return [];
        }

        return $this->normalizeShopeeLines($lines);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    private function normalizeShopeeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = strtoupper(trim((string) ($line['sku'] ?? '')));
            $qty = (float) ($line['qty'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $amount = isset($line['amount']) ? (float) $line['amount'] : null;
            $out[] = ['sku' => $sku, 'qty' => $qty, 'amount' => $amount];
        }

        return $out;
    }

    /**
     * @return list<array{sku: string, qty: float, amount: float|null}>
     */
    private function linesFromReservations(string $channel, string $orderRef): array
    {
        if (! Schema::hasTable('marketplace_on_order_reservations')) {
            return [];
        }

        $rows = MarketplaceOnOrderReservation::query()
            ->where('channel', $channel)
            ->where('order_ref', $orderRef)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $items = Item::query()
            ->whereIn('id', $rows->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all())
            ->get(['id', 'code'])
            ->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($items->get((int) $row->item_id)?->code ?? '')));
            $qty = (float) $row->qty;
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $out[] = ['sku' => $sku, 'qty' => $qty, 'amount' => null];
        }

        return $out;
    }
}