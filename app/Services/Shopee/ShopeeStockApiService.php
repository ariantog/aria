<?php

namespace App\Services\Shopee;

class ShopeeStockApiService
{
    public function __construct(
        private ShopeeStockOpenApiService $openApi,
    ) {}

    public function isReady(): bool
    {
        return $this->openApi->isConfigured() && $this->openApi->hasShopAuthorization();
    }

    public function openApiClient(): ShopeeStockOpenApiService
    {
        return $this->openApi;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPickupWarehouses(): array
    {
        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiGet('/api/v2/shop/get_warehouse_detail', [
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
     * Search Shopee catalog for manual link UI and auto-link discovery.
     *
     * Shopee `search_item` returns `item_id_list` only — hydrate via `get_item_base_info`.
     *
     * @return list<array<string, mixed>>
     */
    public function searchItems(string $keyword, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $pageSize = min(50, max(1, $pageSize));
        $statusFilter = ['NORMAL', 'UNLIST'];

        $itemIds = $this->searchItemIds([
            'page_size' => $pageSize,
            'offset' => 0,
            'item_sku' => $keyword,
            'item_status' => $statusFilter,
        ]);

        if ($itemIds === []) {
            $itemIds = $this->searchItemIds([
                'page_size' => $pageSize,
                'offset' => 0,
                'item_name' => $keyword,
                'item_status' => $statusFilter,
            ]);
        }

        if ($itemIds !== []) {
            return $this->itemSummariesForIds($itemIds);
        }

        return $this->searchUnpackagedModelsAsRows($keyword, $pageSize);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<int>
     */
    private function searchItemIds(array $body): array
    {
        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiPost('/api/v2/product/search_item', $body),
            'Shopee product search',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $ids = $response['item_id_list'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0));
    }

    /**
     * @param  list<int>  $itemIds
     * @return list<array<string, mixed>>
     */
    private function itemSummariesForIds(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiGet('/api/v2/product/get_item_base_info', [
                'item_id_list' => implode(',', array_slice($itemIds, 0, 50)),
            ]),
            'Shopee item base info',
        );

        if ($data === null) {
            return array_map(
                fn (int $id) => ['item_id' => $id, 'item_name' => '', 'item_sku' => ''],
                $itemIds,
            );
        }

        $response = $data['response'] ?? $data;
        $items = $response['item_list'] ?? [];

        if (! is_array($items)) {
            return array_map(
                fn (int $id) => ['item_id' => $id, 'item_name' => '', 'item_sku' => ''],
                $itemIds,
            );
        }

        $rows = [];
        foreach (array_values(array_filter($items, 'is_array')) as $item) {
            $rows[] = [
                'item_id' => (int) ($item['item_id'] ?? 0),
                'item_name' => (string) ($item['item_name'] ?? $item['name'] ?? ''),
                'item_sku' => (string) ($item['item_sku'] ?? $item['sku'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchUnpackagedModelsAsRows(string $keyword, int $pageSize): array
    {
        $body = [
            'page_size' => min(50, max(1, $pageSize)),
            'unpackaged_sku_id' => $keyword,
        ];

        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiPost('/api/v2/product/search_unpackaged_model_list', $body),
            'Shopee unpackaged model search',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $models = $response['model_list'] ?? [];

        if (! is_array($models)) {
            return [];
        }

        $rows = [];
        foreach (array_values(array_filter($models, 'is_array')) as $model) {
            $itemId = (int) ($model['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $rows[] = [
                'item_id' => $itemId,
                'model_id' => (int) ($model['model_id'] ?? 0),
                'item_name' => (string) ($model['item_name'] ?? ''),
                'item_sku' => (string) ($model['model_sku'] ?? $model['unpackaged_sku_id'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function modelsForItem(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiPost('/api/v2/product/get_model_list', [
                'item_id' => $itemId,
            ]),
            'Shopee model list',
        );

        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $models = $response['model'] ?? $response['model_list'] ?? [];

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
