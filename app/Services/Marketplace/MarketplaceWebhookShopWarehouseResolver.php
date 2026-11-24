<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\MarketplaceChannelOrder;
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
    /** @var array<string, ?string> */
    private array $platformWarehouseLabelCache = [];

    public function __construct(
        private MarketplaceWarehouseSyncResolver $warehouseSyncResolver,
        private ShopeeSyncWarehouseResolver $shopeeSyncWarehouseResolver,
        private ShopeeOrderPickupLocation $shopeeOrderPickupLocation,
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
        ?string $channel = null,
    ): int {
        $channel = strtolower(trim((string) $channel));

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE && is_array($payload) && $payload !== []) {
            $shopId = $this->shopRefToPositiveInt($shopRef);
            $hit = $this->shopeeSyncWarehouseResolver->ariaWarehouseIdForIngest($shopId, $payload);
            if ($hit > 0) {
                return $hit;
            }
        }

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

        return $this->ariaWarehouseNameById($warehouseId, $warehouseNames);
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

        if ($row instanceof MarketplaceChannelOrder
            && Schema::hasColumn('marketplace_channel_orders', 'aria_warehouse_id')) {
            $persistedId = (int) ($row->aria_warehouse_id ?? 0);
            if ($persistedId > 0) {
                return $this->ariaWarehouseNameById($persistedId, $warehouseNames);
            }
        }

        $payload = $this->payloadForListRow($row);

        $commercePlatform = isset($row->commerce_platform) ? (string) $row->commerce_platform : null;

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            $warehouseId = $this->resolveAriaWarehouseId(
                $row->shop_ref ?? null,
                $payload,
                $commercePlatform,
                $channel,
            );
            if ($warehouseId > 0) {
                return $this->ariaWarehouseNameById($warehouseId, $warehouseNames);
            }

            return null;
        }

        if ($payload !== null) {
            $warehouseId = $this->resolveAriaWarehouseId(
                $row->shop_ref ?? null,
                $payload,
                $commercePlatform,
                $channel !== '' ? $channel : null,
            );
            if ($warehouseId > 0) {
                return $this->ariaWarehouseNameById($warehouseId, $warehouseNames);
            }
        }

        return $this->warehouseNameForShopRef($row->shop_ref ?? null, $shopRefToWarehouse, $warehouseNames);
    }

    /**
     * Platform warehouse label for list rows (matrix name when mapped, else raw id).
     */
    public function platformWarehouseLabelForListRow(object $row): ?string
    {
        $channel = strtolower((string) ($row->channel ?? ''));
        $platformId = $this->persistedPlatformKeyForListRow($row, $channel);
        if ($platformId === null) {
            $payload = $this->payloadForListRow($row);
            $platformId = $channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE
                ? $this->extractShopeePlatformLocationKey($payload)
                : $this->extractPlatformWarehouseIdFromPayload($payload);
        }
        if ($platformId === null) {
            return null;
        }
        $shopRef = $this->normalizeShopRef($row->shop_ref ?? null);
        $commercePlatform = isset($row->commerce_platform) ? (string) $row->commerce_platform : null;

        if ($shopRef !== '' && Schema::hasTable('marketplace_warehouse_syncs')) {
            $name = $this->lookupPlatformWarehouseName($channel, $shopRef, $platformId, $commercePlatform);
            if ($name !== null && $name !== '') {
                return $name;
            }
        }

        return $platformId;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function payloadForListRow(object $row): ?array
    {
        $payload = is_array($row->payload ?? null) ? $row->payload : null;

        $event = null;
        if ($row instanceof MarketplaceChannelOrder && $row->relationLoaded('lastWebhookEvent')) {
            $event = $row->lastWebhookEvent;
        } elseif (isset($row->lastWebhookEvent)) {
            $event = $row->lastWebhookEvent;
        }

        if ($payload === null && $event !== null && is_array($event->payload ?? null)) {
            $payload = $event->payload;
        }

        $synthetic = $this->syntheticPayloadFromPersistedPlatformWarehouse($row);
        if ($synthetic !== null) {
            if ($payload === null || $this->extractPlatformWarehouseIdFromPayload($payload) === null) {
                return $payload === null
                    ? $synthetic
                    : array_replace_recursive($payload, $synthetic);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function syntheticPayloadFromPersistedPlatformWarehouse(object $row): ?array
    {
        $platformLocationId = null;
        $platformWarehouseId = null;
        if ($row instanceof MarketplaceChannelOrder) {
            $platformLocationId = $row->platform_location_id ?? null;
            $platformWarehouseId = $row->platform_warehouse_id ?? null;
        } else {
            $platformLocationId = $row->platform_location_id ?? null;
            $platformWarehouseId = $row->platform_warehouse_id ?? null;
        }

        if (filled($platformLocationId) && Schema::hasColumn('marketplace_channel_orders', 'platform_location_id')) {
            return [
                'item_list' => [
                    ['product_location_id' => [(string) $platformLocationId]],
                ],
            ];
        }

        if (! filled($platformWarehouseId)
            || ! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')) {
            return null;
        }

        return [
            'data' => [
                'warehouse_id' => (string) $platformWarehouseId,
            ],
        ];
    }

    protected function persistedPlatformKeyForListRow(object $row, string $channel): ?string
    {
        if (! $row instanceof MarketplaceChannelOrder) {
            return null;
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE
            && Schema::hasColumn('marketplace_channel_orders', 'platform_location_id')
            && filled($row->platform_location_id)) {
            return strtoupper(trim((string) $row->platform_location_id));
        }

        if (Schema::hasColumn('marketplace_channel_orders', 'platform_warehouse_id')
            && filled($row->platform_warehouse_id)) {
            return trim((string) $row->platform_warehouse_id);
        }

        return null;
    }

    public function ariaWarehouseNameById(int $warehouseId, array $warehouseNames): ?string
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $name = $warehouseNames[$warehouseId] ?? $warehouseNames[(string) $warehouseId] ?? null;
        if ($name !== null && $name !== '') {
            return (string) $name;
        }

        $loaded = Addrbook::query()->where('id', $warehouseId)->value('name');

        return filled($loaded) ? (string) $loaded : null;
    }

    protected function lookupPlatformWarehouseName(
        string $channel,
        string $shopRef,
        string $platformWarehouseId,
        ?string $commercePlatform,
    ): ?string {
        $cacheKey = $channel.':'.$shopRef.':'.$platformWarehouseId.':'.strtoupper(trim((string) $commercePlatform));
        if (array_key_exists($cacheKey, $this->platformWarehouseLabelCache)) {
            return $this->platformWarehouseLabelCache[$cacheKey];
        }

        $unifiedChannel = $channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE
            ? MarketplaceWarehouseSync::CHANNEL_SHOPEE
            : MarketplaceWarehouseSync::CHANNEL_TIKTOK;

        if (! in_array($unifiedChannel, [MarketplaceWarehouseSync::CHANNEL_SHOPEE, MarketplaceWarehouseSync::CHANNEL_TIKTOK], true)) {
            $this->platformWarehouseLabelCache[$cacheKey] = null;

            return null;
        }

        $name = null;
        if ($unifiedChannel === MarketplaceWarehouseSync::CHANNEL_SHOPEE) {
            $base = MarketplaceWarehouseSync::query()
                ->where('channel', $unifiedChannel)
                ->where('shop_ref', $shopRef)
                ->where('warehouse_id', '>', 0);

            $name = (clone $base)->where('platform_location_id', $platformWarehouseId)->value('platform_warehouse_name');
            if (! filled($name)) {
                $name = (clone $base)->where('platform_warehouse_id', $platformWarehouseId)->value('platform_warehouse_name');
            }
        } else {
            $base = MarketplaceWarehouseSync::query()
                ->where('channel', $unifiedChannel)
                ->where('shop_ref', $shopRef)
                ->where('platform_warehouse_id', $platformWarehouseId);

            if ($unifiedChannel === MarketplaceWarehouseSync::CHANNEL_TIKTOK) {
                $commerce = $this->normalizeCommercePlatform($commercePlatform);
                if ($commerce !== '') {
                    $name = (clone $base)->where('commerce_platform', $commerce)->value('platform_warehouse_name');
                }
                if (! filled($name)) {
                    $name = (clone $base)->where('commerce_platform', '')->value('platform_warehouse_name');
                }
            }

            if (! filled($name)) {
                $name = (clone $base)->value('platform_warehouse_name');
            }
        }

        $name = filled($name) ? trim((string) $name) : null;
        $this->platformWarehouseLabelCache[$cacheKey] = $name;

        return $name;
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

    /**
     * Mapped marketplace shops only (sync tables), not historical webhook noise.
     *
     * @return Collection<int, string>
     */
    public function distinctMappedShopRefsForChannel(?string $channel): Collection
    {
        $channel = $channel === null || $channel === '' ? null : strtolower($channel);
        $refs = collect();

        $includeShopee = $channel === null || $channel === 'shopee';
        $includeTiktok = $channel === null || in_array($channel, ['tiktok', 'tokopedia'], true);

        if ($includeShopee && Schema::hasTable('shopee_syncs')) {
            $refs = $refs->merge(
                Shopeesync::query()
                    ->where('shop_id', '>', 0)
                    ->distinct()
                    ->pluck('shop_id')
                    ->map(fn ($id) => $this->normalizeShopRef((string) $id))
                    ->filter()
            );
        }

        if ($includeTiktok && Schema::hasTable('tiktok_shop_syncs')) {
            $refs = $refs->merge(
                TiktokShopSync::query()
                    ->whereNotNull('shop_ref')
                    ->where('shop_ref', '!=', '')
                    ->distinct()
                    ->pluck('shop_ref')
                    ->map(fn (string $ref) => $this->normalizeShopRef($ref))
                    ->filter()
            );
        }

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            $matrixQuery = MarketplaceWarehouseSync::query()
                ->whereNotNull('shop_ref')
                ->where('shop_ref', '!=', '');

            if ($includeShopee && ! $includeTiktok) {
                $matrixQuery->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE);
            } elseif ($includeTiktok && ! $includeShopee) {
                $matrixQuery->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK);
            }

            $refs = $refs->merge(
                $matrixQuery
                    ->distinct()
                    ->pluck('shop_ref')
                    ->map(fn (string $ref) => $this->normalizeShopRef($ref))
                    ->filter()
            );
        }

        return $refs->unique()->sort()->values();
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

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function extractShopeePlatformLocationKey(?array $payload): ?string
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $pickup = $this->shopeeOrderPickupLocation->extract($payload);
        if ($pickup === null) {
            return null;
        }

        $locationId = strtoupper(trim((string) ($pickup['shopee_location_id'] ?? '')));
        if ($locationId !== '') {
            return $locationId;
        }

        $warehouseId = (int) ($pickup['shopee_warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            return (string) $warehouseId;
        }

        return null;
    }

    /**
     * @param  object{channel?: ?string, shop_ref?: ?string, commerce_platform?: ?string, payload?: mixed}|MarketplaceChannelOrder  $row
     * @return array{aria_name: ?string, platform_label: ?string, unmapped: bool}
     */
    public function warehouseDisplayForRow(
        object $row,
        array $shopRefToWarehouse = [],
        array $warehouseNames = [],
    ): array {
        $ariaName = $this->warehouseNameForListRow($row, $shopRefToWarehouse, $warehouseNames);
        $platformLabel = $this->platformWarehouseLabelForListRow($row);
        $shopRef = $this->normalizeShopRef($row->shop_ref ?? null);

        return [
            'aria_name' => $ariaName,
            'platform_label' => $platformLabel,
            'unmapped' => $shopRef !== '' && $ariaName === null,
        ];
    }

    protected function shopRefToPositiveInt(?string $shopRef): ?int
    {
        $shopRef = $this->normalizeShopRef($shopRef);
        if ($shopRef === '' || ! ctype_digit($shopRef)) {
            return null;
        }

        $id = (int) $shopRef;

        return $id > 0 ? $id : null;
    }
}