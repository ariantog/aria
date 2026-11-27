<?php

namespace App\Services\Marketplace;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class MarketplaceUnlinkedItemsService
{
    public const CHANNEL_SHOPEE = 'shopee';

    public const CHANNEL_TIKTOK = 'tiktok';

    public const CHANNEL_BOTH = 'both';

    /**
     * @param  array{warehouse_id?: int, channel?: string, stock_only?: bool, q?: string}  $filters
     * @return array{
     *     warehouse_id: ?int,
     *     channel: string,
     *     stock_only: bool,
     *     q: string,
     * }
     */
    public function normalizeFilters(array $filters): array
    {
        $channel = strtolower(trim((string) ($filters['channel'] ?? self::CHANNEL_BOTH)));
        if (! in_array($channel, [self::CHANNEL_SHOPEE, self::CHANNEL_TIKTOK, self::CHANNEL_BOTH], true)) {
            $channel = self::CHANNEL_BOTH;
        }

        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);

        return [
            'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
            'channel' => $channel,
            'stock_only' => ! array_key_exists('stock_only', $filters) || (bool) $filters['stock_only'],
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    /**
     * @return list<array{id: int, name: string, channels: list<string>}>
     */
    public function warehouseOptions(): array
    {
        $byId = [];

        foreach ($this->connectedWarehouseIds(null) as $warehouseId) {
            $byId[$warehouseId] = [
                'id' => $warehouseId,
                'name' => '',
                'channels' => [],
            ];
        }

        if ($byId === []) {
            return [];
        }

        $names = Addrbook::withTrashed()
            ->whereIn('id', array_keys($byId))
            ->pluck('name', 'id');

        foreach (array_keys($byId) as $warehouseId) {
            $byId[$warehouseId]['name'] = (string) ($names[$warehouseId] ?? ('Gudang #'.$warehouseId));
            $byId[$warehouseId]['channels'] = $this->channelsForWarehouse($warehouseId);
        }

        return collect($byId)
            ->sortBy(fn (array $row) => $row['name'])
            ->values()
            ->all();
    }

    public function defaultWarehouseId(): ?int
    {
        $options = $this->warehouseOptions();

        return $options[0]['id'] ?? null;
    }

    /**
     * @param  array{warehouse_id?: int, channel?: string, stock_only?: bool, q?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $normalized = $this->normalizeFilters($filters);
        $warehouseId = $normalized['warehouse_id'] ?? $this->defaultWarehouseId();
        if ($warehouseId === null) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $normalized['warehouse_id'] = $warehouseId;

        return $this->buildQuery($normalized)
            ->orderBy('items.code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Item $item) => $this->presentRow($item, $warehouseId, $normalized['channel']));
    }

    /**
     * @param  array{warehouse_id?: int, channel?: string, stock_only?: bool, q?: string}  $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function exportRows(array $filters): \Generator
    {
        $normalized = $this->normalizeFilters($filters);
        $warehouseId = $normalized['warehouse_id'] ?? null;
        if ($warehouseId === null) {
            return;
        }

        $query = $this->buildQuery($normalized)->orderBy('items.code');
        $limit = 10_000;
        $count = 0;

        foreach ($query->lazy(200) as $item) {
            if ($count >= $limit) {
                break;
            }
            $count++;
            yield $this->presentRow($item, $warehouseId, $normalized['channel']);
        }
    }

    public static function isShopeeUnlinked(Item $item): bool
    {
        return (int) ($item->shopee_item_id ?? 0) <= 0
            || (int) ($item->shopee_model_id ?? 0) <= 0;
    }

    public static function isTikTokUnlinked(Item $item): bool
    {
        return ! filled($item->tiktok_shop_product_id)
            || ! filled($item->tiktok_shop_sku_id);
    }

    /**
     * @return list<string>
     */
    public static function missingShopeeFields(Item $item): array
    {
        $missing = [];
        if ((int) ($item->shopee_item_id ?? 0) <= 0) {
            $missing[] = 'shopee_item_id';
        }
        if ((int) ($item->shopee_model_id ?? 0) <= 0) {
            $missing[] = 'shopee_model_id';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public static function missingTikTokFields(Item $item): array
    {
        $missing = [];
        if (! filled($item->tiktok_shop_product_id)) {
            $missing[] = 'tiktok_shop_product_id';
        }
        if (! filled($item->tiktok_shop_sku_id)) {
            $missing[] = 'tiktok_shop_sku_id';
        }

        return $missing;
    }

    /**
     * @param  array{warehouse_id: int, channel: string, stock_only: bool, q: string}  $filters
     */
    protected function buildQuery(array $filters): Builder
    {
        $warehouseId = (int) $filters['warehouse_id'];
        $channel = $filters['channel'];
        $activeChannels = $this->resolveActiveChannels($warehouseId, $channel);

        $query = Item::query()
            ->select([
                'items.*',
                'wi.quantity as warehouse_qty',
            ])
            ->join('warehouse_item as wi', function ($join) use ($warehouseId, $filters) {
                $join->on('wi.item_id', '=', 'items.id')
                    ->where('wi.warehouse_id', '=', $warehouseId);
                if ($filters['stock_only']) {
                    $join->where('wi.quantity', '>', 0);
                }
            })
            ->whereNull('items.deleted_at')
            ->whereIn('items.type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value]);

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('items.code', 'like', '%'.$search.'%')
                    ->orWhere('items.name', 'like', '%'.$search.'%')
                    ->orWhere('items.legacy_code', 'like', '%'.$search.'%');
            });
        }

        $query->where(function (Builder $outer) use ($activeChannels) {
            $applied = false;
            if (in_array(self::CHANNEL_SHOPEE, $activeChannels, true)) {
                $outer->orWhere(fn (Builder $q) => $this->applyShopeeUnlinkedScope($q));
                $applied = true;
            }
            if (in_array(self::CHANNEL_TIKTOK, $activeChannels, true)) {
                $outer->orWhere(fn (Builder $q) => $this->applyTikTokUnlinkedScope($q));
                $applied = true;
            }
            if (! $applied) {
                $outer->whereRaw('0 = 1');
            }
        });

        return $query;
    }

    protected function applyShopeeUnlinkedScope(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNull('items.shopee_item_id')
                ->orWhere('items.shopee_item_id', '<=', 0)
                ->orWhereNull('items.shopee_model_id')
                ->orWhere('items.shopee_model_id', '<=', 0);
        });
    }

    protected function applyTikTokUnlinkedScope(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNull('items.tiktok_shop_product_id')
                ->orWhere('items.tiktok_shop_product_id', '=', '')
                ->orWhereNull('items.tiktok_shop_sku_id')
                ->orWhere('items.tiktok_shop_sku_id', '=', '');
        });
    }

    /**
     * @return list<string>
     */
    protected function resolveActiveChannels(int $warehouseId, string $channelFilter): array
    {
        $supported = $this->channelsForWarehouse($warehouseId);

        if ($channelFilter === self::CHANNEL_SHOPEE) {
            return in_array(self::CHANNEL_SHOPEE, $supported, true) ? [self::CHANNEL_SHOPEE] : [];
        }
        if ($channelFilter === self::CHANNEL_TIKTOK) {
            return in_array(self::CHANNEL_TIKTOK, $supported, true) ? [self::CHANNEL_TIKTOK] : [];
        }

        return array_values(array_intersect($supported, [self::CHANNEL_SHOPEE, self::CHANNEL_TIKTOK]));
    }

    /**
     * @return list<string>
     */
    public function channelsForWarehouse(int $warehouseId): array
    {
        $channels = [];

        if ($warehouseId <= 0) {
            return [];
        }

        if (Schema::hasTable('shopee_syncs')) {
            $hasShopee = Shopeesync::query()
                ->where('warehouse_id', $warehouseId)
                ->exists();
            if ($hasShopee) {
                $channels[] = self::CHANNEL_SHOPEE;
            }
        }

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            if (MarketplaceWarehouseSync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE)
                ->exists()) {
                $channels[] = self::CHANNEL_SHOPEE;
            }
            if (MarketplaceWarehouseSync::query()
                ->where('warehouse_id', $warehouseId)
                ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
                ->exists()) {
                $channels[] = self::CHANNEL_TIKTOK;
            }
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            $hasTiktok = TiktokShopSync::query()
                ->where('warehouse_id', $warehouseId)
                ->exists();
            if ($hasTiktok) {
                $channels[] = self::CHANNEL_TIKTOK;
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * @return list<int>
     */
    public function connectedWarehouseIds(?string $channel): array
    {
        $ids = collect();

        $includeShopee = $channel === null || $channel === self::CHANNEL_BOTH || $channel === self::CHANNEL_SHOPEE;
        $includeTiktok = $channel === null || $channel === self::CHANNEL_BOTH || $channel === self::CHANNEL_TIKTOK;

        if ($includeShopee && Schema::hasTable('shopee_syncs')) {
            $ids = $ids->merge(
                Shopeesync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id')
            );
        }

        if ($includeTiktok && Schema::hasTable('tiktok_shop_syncs')) {
            $ids = $ids->merge(
                TiktokShopSync::query()
                    ->where('warehouse_id', '>', 0)
                    ->distinct()
                    ->pluck('warehouse_id')
            );
        }

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            $matrix = MarketplaceWarehouseSync::query()->where('warehouse_id', '>', 0);
            if ($includeShopee && ! $includeTiktok) {
                $matrix->where('channel', MarketplaceWarehouseSync::CHANNEL_SHOPEE);
            } elseif ($includeTiktok && ! $includeShopee) {
                $matrix->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK);
            }
            $ids = $ids->merge($matrix->distinct()->pluck('warehouse_id'));
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentRow(Item $item, int $warehouseId, string $channelFilter): array
    {
        $channels = $this->channelsForWarehouse($warehouseId);
        $shopeeUnlinked = in_array(self::CHANNEL_SHOPEE, $channels, true) && self::isShopeeUnlinked($item);
        $tiktokUnlinked = in_array(self::CHANNEL_TIKTOK, $channels, true) && self::isTikTokUnlinked($item);

        $missing = [];
        if ($shopeeUnlinked && in_array($channelFilter, [self::CHANNEL_BOTH, self::CHANNEL_SHOPEE], true)) {
            $missing = array_merge($missing, self::missingShopeeFields($item));
        }
        if ($tiktokUnlinked && in_array($channelFilter, [self::CHANNEL_BOTH, self::CHANNEL_TIKTOK], true)) {
            $missing = array_merge($missing, self::missingTikTokFields($item));
        }

        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'warehouse_qty' => (float) ($item->warehouse_qty ?? 0),
            'shopee_unlinked' => $shopeeUnlinked,
            'tiktok_unlinked' => $tiktokUnlinked,
            'missing_fields' => array_values(array_unique($missing)),
            'show_url' => $item->showUrl(),
            'shopee_link_url' => route('items.shopee', $item),
            'tiktok_link_url' => route('items.tiktok-shop', $item),
        ];
    }
}