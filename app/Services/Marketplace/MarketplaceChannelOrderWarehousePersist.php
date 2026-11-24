<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWebhookEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve and persist platform / Aria warehouse keys on marketplace_channel_orders.
 */
class MarketplaceChannelOrderWarehousePersist
{
    public function __construct(
        private MarketplaceWebhookShopWarehouseResolver $shopWarehouseResolver,
    ) {}

    public function hasPersistedWarehouseSnapshot(MarketplaceChannelOrder $order): bool
    {
        if ($this->hasAriaWarehouseColumn() && (int) ($order->aria_warehouse_id ?? 0) > 0) {
            return true;
        }

        if ($this->hasPlatformLocationColumn() && filled($order->platform_location_id ?? null)) {
            return true;
        }

        if ($this->hasPlatformWarehouseColumn() && filled($order->platform_warehouse_id ?? null)) {
            return true;
        }

        return false;
    }

    public function persistFromWebhookEvent(MarketplaceChannelOrder $order, MarketplaceWebhookEvent $event): void
    {
        $payload = is_array($event->payload) ? $event->payload : null;
        if ($payload === null) {
            return;
        }

        $this->applyResolvedFields($order, $payload, save: true);
    }

    /**
     * @param  EloquentCollection<int, MarketplaceChannelOrder>|Collection<int, MarketplaceChannelOrder>  $orders
     */
    public function hydrateListPage(EloquentCollection|Collection $orders): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        $needing = $orders->filter(fn (MarketplaceChannelOrder $order) => ! $this->hasPersistedWarehouseSnapshot($order));
        if ($needing->isEmpty()) {
            return;
        }

        $shadowPayloads = $this->loadShadowPushPayloads($needing);
        $webhookPayloads = $this->loadWebhookPayloadsWithWarehouseHint($needing);

        foreach ($needing as $order) {
            $channel = strtolower((string) $order->channel);
            $ref = (string) $order->order_ref;

            $payload = null;
            if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE && isset($shadowPayloads[$ref])) {
                $payload = $shadowPayloads[$ref];
            }
            if ($payload === null && isset($webhookPayloads[$ref])) {
                $payload = $webhookPayloads[$ref];
            }
            if ($payload === null && $order->relationLoaded('lastWebhookEvent')) {
                $last = $order->lastWebhookEvent;
                if ($last !== null && is_array($last->payload)) {
                    $payload = $last->payload;
                }
            }

            if ($payload === null) {
                continue;
            }

            $this->applyResolvedFields($order, $payload, save: true);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyResolvedFields(MarketplaceChannelOrder $order, array $payload, bool $save = false): bool
    {
        $channel = strtolower((string) $order->channel);
        $shopRef = $order->shop_ref;
        $commercePlatform = filled($order->commerce_platform) ? (string) $order->commerce_platform : null;

        $warehouseId = $this->shopWarehouseResolver->resolveAriaWarehouseId(
            $shopRef,
            $payload,
            $commercePlatform,
            $channel,
        );

        $platformLocationId = null;
        $platformWarehouseId = null;

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            $pickup = app(ShopeeOrderPickupLocation::class)->extract($payload);
            if ($pickup !== null) {
                $loc = strtoupper(trim((string) ($pickup['shopee_location_id'] ?? '')));
                if ($loc !== '') {
                    $platformLocationId = $loc;
                }
                $wh = (int) ($pickup['shopee_warehouse_id'] ?? 0);
                if ($wh > 0 && ! filled($platformLocationId)) {
                    $platformWarehouseId = (string) $wh;
                }
            }
        } else {
            $platformWarehouseId = $this->shopWarehouseResolver->extractPlatformWarehouseIdFromPayload($payload);
        }

        $dirty = false;

        if ($warehouseId > 0 && $this->hasAriaWarehouseColumn() && (int) ($order->aria_warehouse_id ?? 0) <= 0) {
            $order->aria_warehouse_id = $warehouseId;
            $dirty = true;
        }

        if (filled($platformLocationId) && $this->hasPlatformLocationColumn() && ! filled($order->platform_location_id ?? null)) {
            $order->platform_location_id = $platformLocationId;
            $dirty = true;
        }

        if (filled($platformWarehouseId) && $this->hasPlatformWarehouseColumn() && ! filled($order->platform_warehouse_id ?? null)) {
            $order->platform_warehouse_id = $platformWarehouseId;
            $dirty = true;
        }

        if ($dirty && $save) {
            $order->save();
        }

        return $dirty;
    }

    /**
     * @param  Collection<int, MarketplaceChannelOrder>  $orders
     * @return array<string, array<string, mixed>>
     */
    private function loadShadowPushPayloads(Collection $orders): array
    {
        if (! Schema::hasTable('marketplace_order_shadows')) {
            return [];
        }

        $shopeeRefs = $orders
            ->filter(fn (MarketplaceChannelOrder $o) => strtolower((string) $o->channel) === MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->pluck('order_ref')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($shopeeRefs === []) {
            return [];
        }

        $out = [];
        MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->whereIn('external_order_sn', $shopeeRefs)
            ->get(['external_order_sn', 'channel_payload'])
            ->each(function (MarketplaceOrderShadow $shadow) use (&$out): void {
                $sn = (string) $shadow->external_order_sn;
                if ($sn === '' || ! is_array($shadow->channel_payload)) {
                    return;
                }

                $orderRow = $shadow->channel_payload['response']['order_list'][0] ?? null;
                if (! is_array($orderRow)) {
                    return;
                }

                $payload = [
                    'item_list' => $orderRow['item_list'] ?? [],
                ];
                if (isset($orderRow['package_list'])) {
                    $payload['package_list'] = $orderRow['package_list'];
                }

                if ($this->payloadHasWarehouseHint(MarketplaceWebhookRecorder::CHANNEL_SHOPEE, $payload)) {
                    $out[$sn] = $payload;
                }
            });

        return $out;
    }

    /**
     * @param  Collection<int, MarketplaceChannelOrder>  $orders
     * @return array<string, array<string, mixed>>
     */
    private function loadWebhookPayloadsWithWarehouseHint(Collection $orders): array
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            return [];
        }

        $byChannel = $orders->groupBy(fn (MarketplaceChannelOrder $o) => strtolower((string) $o->channel));
        $out = [];

        foreach ($byChannel as $channel => $group) {
            $refs = $group->pluck('order_ref')->filter()->unique()->values()->all();
            if ($refs === []) {
                continue;
            }

            MarketplaceWebhookEvent::query()
                ->where('channel', $channel)
                ->whereIn('order_ref', $refs)
                ->where('signature_ok', true)
                ->orderByDesc('id')
                ->get(['order_ref', 'payload'])
                ->each(function (MarketplaceWebhookEvent $event) use (&$out, $channel): void {
                    $ref = (string) $event->order_ref;
                    if ($ref === '' || isset($out[$ref])) {
                        return;
                    }
                    $payload = is_array($event->payload) ? $event->payload : null;
                    if ($payload === null || ! $this->payloadHasWarehouseHint($channel, $payload)) {
                        return;
                    }
                    $out[$ref] = $payload;
                });
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHasWarehouseHint(string $channel, array $payload): bool
    {
        $channel = strtolower($channel);
        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            return $this->shopWarehouseResolver->extractShopeePlatformLocationKey($payload) !== null;
        }

        return $this->shopWarehouseResolver->extractPlatformWarehouseIdFromPayload($payload) !== null;
    }

    private function hasAriaWarehouseColumn(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'aria_warehouse_id');
    }

    private function hasPlatformLocationColumn(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'platform_location_id');
    }

    private function hasPlatformWarehouseColumn(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id');
    }
}