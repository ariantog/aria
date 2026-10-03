<?php

namespace App\Services\Shopee;

use App\Services\ShopeeAds\ShopeeAdsApiService;
class ShopeeStockApiService
{
    public function __construct(
        private ShopeeAdsApiService $adsApi,
    ) {}

    public function isReady(): bool
    {
        return $this->adsApi->isConfigured() && $this->adsApi->hasShopAuthorization();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPickupWarehouses(): array
    {
        $data = $this->adsApi->decodeShopResponse(
            $this->adsApi->shopApiGet('/api/v2/shop/get_warehouse_detail', [
                'warehouse_type' => 1,
            ]),
            'Shopee warehouse list',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $rows = $response['warehouse_list'] ?? $response ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchItems(string $keyword, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $data = $this->adsApi->decodeShopResponse(
            $this->adsApi->shopApiPost('/api/v2/product/search_item', [
                'item_sku' => $keyword,
                'page_size' => min(50, max(1, $pageSize)),
                'offset' => 0,
            ]),
            'Shopee product search',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $items = $response['item_list'] ?? $response['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function modelsForItem(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $data = $this->adsApi->decodeShopResponse(
            $this->adsApi->shopApiPost('/api/v2/product/get_model_list', [
                'item_id' => $itemId,
            ]),
            'Shopee model list',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $models = $response['model'] ?? [];

        return is_array($models) ? array_values(array_filter($models, 'is_array')) : [];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function modelsByItemIds(array $itemIds): array
    {
        $out = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $itemIds), fn ($id) => $id > 0))) as $itemId) {
            $models = $this->modelsForItem($itemId);
            if ($models !== []) {
                $out[$itemId] = $models;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stockSnapshotForItem(int $itemId, int $modelId, ?string $locationId, ?string $modelSku = null): ?array
    {
        $models = $this->modelsForItem($itemId);
        if ($models === []) {
            return null;
        }

        $model = ShopeeModelStock::pickModel($models, $modelId, $modelSku);
        if ($model === null) {
            return null;
        }

        $sellable = ShopeeModelStock::sellableQuantity($model, $locationId);
        $reserved = ShopeeModelStock::reservedQuantity($model);

        return [
            'item_id' => $itemId,
            'model_id' => (int) ($model['model_id'] ?? 0),
            'model_sku' => (string) ($model['model_sku'] ?? ''),
            'sellable' => $sellable,
            'reserved' => $reserved,
            'location_breakdown' => self::locationBreakdown($model),
        ];
    }

    /**
     * @param  array<string, mixed>  $model
     * @return list<array{location_id: string, stock: int}>
     */
    public static function locationBreakdown(array $model): array
    {
        $stockInfoV2 = $model['stock_info_v2'] ?? null;
        if (! is_array($stockInfoV2)) {
            return [];
        }

        $rows = [];
        foreach ($stockInfoV2['seller_stock'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = [
                'location_id' => (string) ($row['location_id'] ?? ''),
                'stock' => (int) ($row['stock'] ?? 0),
            ];
        }

        return $rows;
    }
}
