<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Best-effort shop_ref → Aria warehouse mapping for webhook inbox filters.
 *
 * Primary: `marketplace_warehouse_syncs`. Legacy: shopee_syncs / tiktok_shop_syncs.
 */
class MarketplaceWebhookShopWarehouseResolver
{
    public function __construct(
        private MarketplaceWarehouseSyncResolver $warehouseSyncResolver,
    ) {}
    /**
     * @return array<int, string> warehouse_id => addrbook name
     */
    public function warehouseOptions(): array
    {
        $warehouseIds = collect();

        if (Schema::hasTable('shopee_syncs')) {
            $warehouseIds = $warehouseIds->merge(
                Shopeesync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id'),
            );
        }

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            $warehouseIds = $warehouseIds->merge(
                MarketplaceWarehouseSync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id'),
            );
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            $warehouseIds = $warehouseIds->merge(
                TiktokShopSync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id'),
            );
        }

        $warehouseIds = $warehouseIds->unique()->filter(fn ($id) => (int) $id > 0)->values();

        if ($warehouseIds->isEmpty()) {
            return [];
        }

        return Addrbook::query()
            ->whereIn('id', $warehouseIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<string> shop_ref values tied to the warehouse (Shopee + TikTok).
     */
    public function shopRefsForWarehouse(int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return [];
        }

        $refs = [];

        if (Schema::hasTable('shopee_syncs')) {
            $refs = array_merge($refs, Shopeesync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('shop_id', '>', 0)
                ->pluck('shop_id')
                ->map(fn ($id) => (string) $id)
                ->all());
        }

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            $refs = array_merge($refs, MarketplaceWarehouseSync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('shop_ref', '!=', '')
                ->pluck('shop_ref')
                ->map(fn ($ref) => (string) $ref)
                ->all());
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            $refs = array_merge($refs, TiktokShopSync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('shop_ref', '!=', '')
                ->pluck('shop_ref')
                ->map(fn ($ref) => (string) $ref)
                ->all());
        }

        return collect($refs)->unique()->values()->all();
    }

    /**
     * @return array<string, int> shop_ref => warehouse_id (first row wins per channel table order)
     */
    public function shopRefToWarehouseMap(): array
    {
        $map = [];

        if (Schema::hasTable('shopee_syncs')) {
            Shopeesync::query()
                ->where('warehouse_id', '>', 0)
                ->where('shop_id', '>', 0)
                ->orderBy('id')
                ->get(['shop_id', 'warehouse_id'])
                ->each(function (Shopeesync $row) use (&$map): void {
                    $ref = $this->normalizeShopRef((string) $row->shop_id);
                    if ($ref !== '' && ! array_key_exists($ref, $map)) {
                        $map[$ref] = (int) $row->warehouse_id;
                    }
                });
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            TiktokShopSync::query()
                ->where('warehouse_id', '>', 0)
                ->where('shop_ref', '!=', '')
                ->orderBy('id')
                ->get(['shop_ref', 'warehouse_id'])
                ->each(function (TiktokShopSync $row) use (&$map): void {
                    $ref = $this->normalizeShopRef((string) $row->shop_ref);
                    if ($ref !== '' && ! array_key_exists($ref, $map)) {
                        $map[$ref] = (int) $row->warehouse_id;
                    }
                });
        }

        return $map;
    }

    public function warehouseIdForShopRef(?string $shopRef): int
    {
        $shopRef = $this->normalizeShopRef($shopRef);
        if ($shopRef === '') {
            return 0;
        }

        return (int) ($this->shopRefToWarehouseMap()[$shopRef] ?? 0);
    }

    public function warehouseIdForTikTokShopRef(
        ?string $shopRef,
        ?string $platformWarehouseId = null,
        ?string $commercePlatform = null,
    ): int {
        $shopRef = $this->normalizeShopRef($shopRef);
        $platformWarehouseId = trim((string) $platformWarehouseId);

        if ($shopRef === '' || $platformWarehouseId === '') {
            return $this->warehouseIdForShopRef($shopRef);
        }

        $hit = $this->warehouseSyncResolver->ariaWarehouseForTikTok(
            $shopRef,
            $platformWarehouseId,
            $commercePlatform,
        );
        if ($hit > 0) {
            return $hit;
        }

        return $this->warehouseIdForShopRef($shopRef);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function resolveAriaWarehouseId(
        ?string $shopRef,
        ?array $payload = null,
        ?string $commercePlatform = null,
    ): int {
        $platformWarehouseId = $this->extractPlatformWarehouseIdFromPayload($payload);
        if ($platformWarehouseId !== null) {
            return $this->warehouseIdForTikTokShopRef($shopRef, $platformWarehouseId, $commercePlatform);
        }

        return $this->warehouseIdForShopRef($shopRef);
    }

    public function warehouseNameForShopRef(?string $shopRef, array $shopRefToWarehouse, array $warehouseNames): ?string
    {
        $shopRef = $this->normalizeShopRef($shopRef);
        if ($shopRef === '') {
            return null;
        }

        $warehouseId = $shopRefToWarehouse[$shopRef] ?? $this->warehouseIdForShopRef($shopRef);
        if ($warehouseId <= 0) {
            return null;
        }

        return $warehouseNames[$warehouseId] ?? $warehouseNames[(string) $warehouseId] ?? null;
    }

    /**
     * Gudang label for omnichannel list rows (TikTok uses platform warehouse when present in payload).
     *
     * @param  object{shop_ref?: ?string, channel?: ?string, commerce_platform?: ?string, payload?: mixed}  $row
     */
    public function warehouseNameForListRow(
        object $row,
        array $shopRefToWarehouse,
        array $warehouseNames,
    ): ?string {
        $channel = strtolower((string) ($row->channel ?? ''));

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            $payload = is_array($row->payload ?? null) ? $row->payload : null;
            $warehouseId = $this->resolveAriaWarehouseId(
                $row->shop_ref ?? null,
                $payload,
                isset($row->commerce_platform) ? (string) $row->commerce_platform : null,
            );
            if ($warehouseId > 0) {
                return $warehouseNames[$warehouseId] ?? $warehouseNames[(string) $warehouseId] ?? null;
            }

            return null;
        }

        return $this->warehouseNameForShopRef($row->shop_ref ?? null, $shopRefToWarehouse, $warehouseNames);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function extractPlatformWarehouseIdFromPayload(?array $payload): ?string
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $paths = [
            $payload['data']['warehouse_id'] ?? null,
            $payload['data']['shipping_warehouse_id'] ?? null,
            $payload['data']['fulfillment_warehouse_id'] ?? null,
            $payload['warehouse_id'] ?? null,
        ];

        foreach ($paths as $candidate) {
            $normalized = $this->normalizePlatformWarehouseId($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        return $this->findPlatformWarehouseIdRecursive($data, 0);
    }

    public function normalizeShopRef(?string $shopRef): string
    {
        $shopRef = trim((string) $shopRef);
        if ($shopRef === '') {
            return '';
        }

        if (ctype_digit($shopRef)) {
            return (string) (int) $shopRef;
        }

        return $shopRef;
    }

    /**
     * @return Collection<int, string> distinct shop_ref for filter dropdown
     */
    public function distinctShopRefsForChannel(?string $channel): Collection
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            return collect();
        }

        $query = \App\Models\MarketplaceWebhookEvent::query()
            ->whereNotNull('shop_ref')
            ->where('shop_ref', '!=', '');

        if (filled($channel) && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        return $query
            ->distinct()
            ->orderBy('shop_ref')
            ->limit(200)
            ->pluck('shop_ref');
    }

    protected function normalizeCommercePlatform(?string $commercePlatform): string
    {
        $commercePlatform = strtoupper(trim((string) $commercePlatform));
        if ($commercePlatform === '') {
            return '';
        }

        if ($commercePlatform === 'TOKOPEDIA') {
            return 'TOKOPEDIA';
        }

        if (in_array($commercePlatform, ['TIKTOK_SHOP', 'TIKTOK', 'TTS'], true)) {
            return 'TIKTOK_SHOP';
        }

        return '';
    }

    protected function normalizePlatformWarehouseId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $id = trim((string) $value);
            if ($id === '' || $id === '0') {
                return null;
            }

            return $id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function findPlatformWarehouseIdRecursive(array $node, int $depth): ?string
    {
        if ($depth > 6) {
            return null;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, [
                'warehouse_id',
                'shipping_warehouse_id',
                'fulfillment_warehouse_id',
                'seller_warehouse_id',
            ], true)) {
                $normalized = $this->normalizePlatformWarehouseId($value);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->findPlatformWarehouseIdRecursive($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}