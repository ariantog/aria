<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWebhookEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only order detail from stored webhook/shadow rows; optional TikTok commerce_platform enrich.
 */
class OmnichannelOrderDetailReader
{
    public function __construct(
        protected OmnichannelReceivedTimes $receivedTimes,
        protected MarketplaceTikTokCommercePlatformEnricher $commercePlatformEnricher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $channel, string $orderRef): array
    {
        $channel = strtolower($channel);
        $orderRef = trim($orderRef);

        $channelOrder = null;
        $commercePlatform = null;
        if (Schema::hasTable('marketplace_channel_orders')) {
            $channelOrder = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->first();
            $commercePlatform = filled($channelOrder?->commerce_platform)
                ? strtoupper((string) $channelOrder->commerce_platform)
                : null;
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK && $commercePlatform === null) {
            $commercePlatform = $this->commercePlatformEnricher->enrichOrder($orderRef);
        }

        $webhookEvents = [];
        if (Schema::hasTable('marketplace_webhook_events')) {
            $webhookEvents = MarketplaceWebhookEvent::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'event_type', 'order_status', 'shop_ref', 'signature_ok', 'received_at', 'commerce_platform'])
                ->map(fn (MarketplaceWebhookEvent $row) => [
                    'id' => $row->id,
                    'event_type' => $row->event_type,
                    'order_status' => $row->order_status,
                    'shop_ref' => $row->shop_ref,
                    'signature_ok' => (bool) $row->signature_ok,
                    'received_at' => $row->received_at?->toIso8601String(),
                    'commerce_platform' => filled($row->commerce_platform)
                        ? strtoupper((string) $row->commerce_platform)
                        : null,
                ])
                ->all();
        }

        $shadow = null;
        $ingress = null;
        $shadowModel = null;
        if ($channel === MarketplaceOrderShadow::CHANNEL_SHOPEE && Schema::hasTable('marketplace_order_shadows')) {
            $shadowModel = MarketplaceOrderShadow::query()
                ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
                ->where('external_order_sn', $orderRef)
                ->first();

            if ($shadowModel !== null) {
                $shadow = [
                    'id' => $shadowModel->id,
                    'compare_status' => $shadowModel->compare_status,
                    'line_compare_status' => $shadowModel->line_compare_status,
                    'channel_status' => $shadowModel->channel_status,
                    'compare_note' => $shadowModel->compare_note,
                    'line_summary' => $shadowModel->line_summary,
                    'jubelio_line_summary' => $shadowModel->jubelio_line_summary,
                    'detail_loaded' => $shadowModel->detailLoaded(),
                ];
                $ingress = $this->receivedTimes->forSingle($shadowModel);
            }
        }

        $jubelioOrder = $this->resolveJubelioOrder($channel, $orderRef, $shadowModel);

        return [
            'channel' => $channel,
            'order_ref' => $orderRef,
            'commerce_platform' => $commercePlatform,
            'webhook_events' => $webhookEvents,
            'shadow' => $shadow,
            'ingress' => $ingress,
            'jubelio' => $jubelioOrder !== null ? [
                'id' => $jubelioOrder->id,
                'invoice' => $jubelioOrder->invoice,
                'order_status' => $jubelioOrder->order_status,
                'status' => $jubelioOrder->status,
                'show_url' => Gate::check(\App\Models\Jubelio::getPermissions()['view'])
                    ? route('jubelio.show', $jubelioOrder->id)
                    : null,
            ] : null,
            'links' => $this->resolveLinks($channel, $orderRef, $jubelioOrder),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveLinks(string $channel, string $orderRef, ?Jubelioorder $jubelioOrder): array
    {
        $links = [
            'shadow_compare' => null,
            'jubelio' => null,
        ];

        if ($channel === MarketplaceOrderShadow::CHANNEL_SHOPEE) {
            $links['shadow_compare'] = route('omnichannel.shopee.show', $orderRef);
        }

        if ($jubelioOrder !== null && Gate::check(\App\Models\Jubelio::getPermissions()['view'])) {
            $links['jubelio'] = route('jubelio.show', $jubelioOrder->id);
        }

        return $links;
    }

    private function resolveJubelioOrder(
        string $channel,
        string $orderRef,
        ?MarketplaceOrderShadow $shadow,
    ): ?Jubelioorder {
        if ($shadow?->jubelio_order_id) {
            $hit = Jubelioorder::query()->find($shadow->jubelio_order_id);
            if ($hit !== null) {
                return $hit;
            }
        }

        if (filled($shadow?->jubelio_invoice)) {
            $hit = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $shadow->jubelio_invoice)
                ->first();
            if ($hit !== null) {
                return $hit;
            }
        }

        $query = Jubelioorder::query()->where('type', 'SELL');
        $query->where(function ($q) use ($channel, $orderRef): void {
            if ($channel === 'shopee') {
                $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));
                $base = $prefix.'-'.$orderRef;
                $q->where('invoice', $base)
                    ->orWhere('invoice', 'like', $prefix.'-'.$orderRef.'-%');
            } elseif ($channel === 'tiktok') {
                $q->where('invoice', $orderRef)
                    ->orWhere('invoice', 'like', 'TT-'.$orderRef.'%')
                    ->orWhere('invoice', 'like', '%'.$orderRef.'%');
            } else {
                $q->where('invoice', $orderRef);
            }
        });

        return $query->orderByDesc('id')->first();
    }
}