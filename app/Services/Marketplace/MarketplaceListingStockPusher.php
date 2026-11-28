<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\MarketplaceListingStockPush;
use App\Models\MarketplaceWarehouseSync;
use App\Services\Shopee\ShopeeListingStockWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class MarketplaceListingStockPusher
{
    public const TRIGGER_RETURN_WEBHOPEE_CORRECT = 'return_webhook_shopee_correct';

    public const TRIGGER_RETURN_VERIFY_ALL_CHANNELS = 'return_verify_all_channels';

    public function __construct(
        private MarketplaceListingPushAllowlist $allowlist,
        private MarketplaceListingSellableCalculator $sellable,
        private MarketplaceListingPushTargetResolver $targets,
        private ShopeeListingStockWriter $shopeeWriter,
        private TikTokListingStockWriter $tiktokWriter,
    ) {}

    /**
     * Push Shopee listing qty for allowlisted SKUs on all mapped locations for the shop (no warehouse write).
     *
     * @return array{pushed: int, skipped: int, errors: list<string>}
     */
    public function pushShopeeShopSellableForItem(
        Item $item,
        ?int $shopId,
        string $trigger,
        ?string $orderRef = null,
    ): array {
        $stats = ['pushed' => 0, 'skipped' => 0, 'errors' => []];

        if (! $this->allowlist->allowsItem($item)) {
            $stats['skipped']++;
            $stats['errors'][] = 'Item '.$item->id.' not on MARKETPLACE_STOCK_PUSH_ITEM_IDS.';

            return $stats;
        }

        foreach ($this->targets->shopeeTargetsForShop($shopId) as $target) {
            $result = $this->pushOne(
                $item,
                $target,
                $trigger,
                $orderRef,
                channels: [MarketplaceWarehouseSync::CHANNEL_SHOPEE],
            );
            $stats['pushed'] += $result['pushed'];
            $stats['skipped'] += $result['skipped'];
            $stats['errors'] = array_merge($stats['errors'], $result['errors']);
        }

        return $stats;
    }

    /**
     * After verify (+ post): push sellable to every mapped marketplace warehouse for line SKUs.
     *
     * @param  list<array{sku: string, qty: float}>  $lines
     * @return array{pushed: int, skipped: int, errors: list<string>}
     */
    public function pushAllChannelsForReturnLines(
        array $lines,
        int $ariaWarehouseId,
        ?string $orderRef = null,
    ): array {
        $stats = ['pushed' => 0, 'skipped' => 0, 'errors' => []];
        $targets = $this->targets->targetsForWarehouse($ariaWarehouseId);

        foreach ($lines as $line) {
            $sku = strtoupper(trim((string) ($line['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $item = Item::query()->whereRaw('UPPER(code) = ?', [$sku])->first();
            if ($item === null) {
                $stats['skipped']++;
                $stats['errors'][] = 'SKU '.$sku.' tidak ditemukan.';

                continue;
            }

            if (! $this->allowlist->allowsItem($item)) {
                $stats['skipped']++;

                continue;
            }

            foreach ($targets as $target) {
                $result = $this->pushOne(
                    $item,
                    $target,
                    self::TRIGGER_RETURN_VERIFY_ALL_CHANNELS,
                    $orderRef,
                );
                $stats['pushed'] += $result['pushed'];
                $stats['skipped'] += $result['skipped'];
                $stats['errors'] = array_merge($stats['errors'], $result['errors']);
            }
        }

        return $stats;
    }

    /**
     * @param  list<string>|null  $channels  null = infer from target
     * @return array{pushed: int, skipped: int, errors: list<string>}
     */
    private function pushOne(
        Item $item,
        array $target,
        string $trigger,
        ?string $orderRef,
        ?array $channels = null,
    ): array {
        $stats = ['pushed' => 0, 'skipped' => 0, 'errors' => []];
        $channel = strtolower((string) ($target['channel'] ?? ''));
        if ($channels !== null && ! in_array($channel, $channels, true)) {
            $stats['skipped']++;

            return $stats;
        }

        $warehouseId = (int) ($target['warehouse_id'] ?? 0);
        $qty = $this->sellable->listingQtyForWarehouseItem((int) $item->id, $warehouseId);
        $shopRef = (string) ($target['shop_ref'] ?? '');
        $locationId = (string) ($target['platform_location_id'] ?? '');

        $idempotencyKey = $this->idempotencyKey(
            (int) $item->id,
            $channel,
            $shopRef,
            $locationId,
            $trigger,
            $orderRef,
            $qty,
        );

        if ($this->shouldSkipIdempotent($idempotencyKey)) {
            $stats['skipped']++;

            return $stats;
        }

        $outcome = 'failed';
        $httpStatus = 0;
        $snippet = null;
        $error = null;

        if ($channel === MarketplaceWarehouseSync::CHANNEL_SHOPEE) {
            $write = $this->shopeeWriter->pushLocationStock($item, $locationId, $qty);
            $httpStatus = (int) ($write['http_status'] ?? 0);
            $snippet = $write['snippet'] ?? null;
            $error = $write['error'] ?? null;
            if ($write['ok'] ?? false) {
                $outcome = 'success';
                $stats['pushed']++;
            } else {
                $stats['errors'][] = trim(($error ?? 'Shopee push failed').' item='.$item->id);
            }
        } elseif ($channel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
            $write = $this->tiktokWriter->pushSellable(
                $item,
                $shopRef,
                (string) ($target['shop_cipher'] ?? ''),
                (string) ($target['platform_warehouse_id'] ?? ''),
                $qty,
                (string) ($target['commerce_platform'] ?? ''),
            );
            $httpStatus = (int) ($write['http_status'] ?? 0);
            $snippet = $write['snippet'] ?? null;
            if ($write['skipped'] ?? false) {
                $outcome = 'skipped';
                $stats['skipped']++;
            } else {
                $stats['errors'][] = 'TikTok push not wired item='.$item->id;
            }
        } else {
            $outcome = 'skipped';
            $stats['skipped']++;
        }

        $this->recordAudit(
            $item,
            $channel,
            $shopRef,
            $locationId,
            $trigger,
            $idempotencyKey,
            $orderRef,
            $qty,
            $httpStatus,
            $outcome,
            $snippet,
        );

        return $stats;
    }

    private function idempotencyKey(
        int $itemId,
        string $channel,
        string $shopRef,
        string $locationId,
        string $trigger,
        ?string $orderRef,
        int $qty,
    ): string {
        $orderPart = filled($orderRef) ? $orderRef : '_';

        return substr(hash('sha256', implode('|', [
            $trigger,
            $channel,
            $shopRef,
            $locationId,
            (string) $itemId,
            $orderPart,
            (string) $qty,
        ])), 0, 64);
    }

    private function shouldSkipIdempotent(string $idempotencyKey): bool
    {
        if (! Schema::hasTable('marketplace_listing_stock_pushes')) {
            return false;
        }

        $ttlMinutes = max(5, (int) config('marketplace.listing_push.idempotency_ttl_minutes', 120));
        $since = Carbon::now()->subMinutes($ttlMinutes);

        return MarketplaceListingStockPush::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('outcome', 'success')
            ->where(function ($q) use ($since) {
                $q->where('pushed_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->exists();
    }

    private function recordAudit(
        Item $item,
        string $channel,
        string $shopRef,
        string $locationId,
        string $trigger,
        string $idempotencyKey,
        ?string $orderRef,
        int $qty,
        int $httpStatus,
        string $outcome,
        ?string $snippet,
    ): void {
        if (! Schema::hasTable('marketplace_listing_stock_pushes')) {
            return;
        }

        MarketplaceListingStockPush::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'item_id' => (int) $item->id,
                'channel' => $channel,
                'shop_ref' => $shopRef,
                'platform_location_id' => $locationId,
                'trigger' => $trigger,
                'order_ref' => $orderRef,
                'requested_qty' => $qty,
                'http_status' => $httpStatus > 0 ? $httpStatus : null,
                'outcome' => $outcome,
                'response_snippet' => $snippet,
                'pushed_at' => $outcome === 'success' ? now() : null,
            ],
        );
    }
}