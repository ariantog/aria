<?php

namespace App\Services\Shopee;

class ShopeeStockApiService
{
    /** Max `search_item` calls when resolving one keyword (name fragments only). */
    private const MAX_NAME_SEARCH_QUERIES = 4;

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
     * Manual link UI — Shopee does not reliably search by merchant variant SKU.
     * Uses `search_item` with `item_name` fragments, then hydrates IDs.
     *
     * @return list<array<string, mixed>>
     */
    public function searchItems(string $keyword, int $pageSize = 20): array
    {
        return $this->discoverCandidatesForSku($keyword, $pageSize);
    }

    /**
     * Auto-link discovery — same name search as UI, no unsupported SKU API calls.
     *
     * @return list<array<string, mixed>>
     */
    public function discoverCandidatesForSku(string $keyword, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $pageSize = min(50, max(1, $pageSize));

        if (ctype_digit($keyword)) {
            $itemId = (int) $keyword;
            if ($itemId > 0) {
                return $this->itemSummariesForIds([$itemId]);
            }
        }

        $statusFilter = ['NORMAL', 'UNLIST'];
        $itemIds = [];

        foreach ($this->itemNameSearchQueries($keyword) as $query) {
            foreach ($this->searchItemIdsByName($query, $pageSize, $statusFilter) as $id) {
                $itemIds[$id] = true;
            }

            if (count($itemIds) >= $pageSize) {
                break;
            }
        }

        if ($itemIds !== []) {
            return $this->itemSummariesForIds(array_slice(array_keys($itemIds), 0, $pageSize));
        }

        if ($this->looksLikeVariationCode($keyword)) {
            return $this->searchRowsForExactModelSku($keyword);
        }

        return [];
    }

    /**
     * Kode Variasi / model_sku (e.g. KNEEWRAP-01-REDIRON) — not searchable via search_item; scan catalog.
     *
     * @return list<array<string, mixed>>
     */
    public function searchRowsForExactModelSku(string $modelSku, int $catalogOffset = 0): array
    {
        $scan = $this->findExactModelSkuOnCatalogPage($modelSku, $catalogOffset, 40, 15);
        if ($scan['matches'] === []) {
            return [];
        }

        $rows = [];
        foreach ($scan['matches'] as $match) {
            $itemId = (int) ($match['item_id'] ?? 0);
            $modelId = (int) ($match['model_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $summary = $this->itemSummariesForIds([$itemId]);
            $name = (string) ($summary[0]['item_name'] ?? '');
            $parentSku = (string) ($summary[0]['item_sku'] ?? '');

            $modelSkuLabel = strtoupper(trim($modelSku));
            if ($modelId > 0) {
                foreach ($this->modelsForItem($itemId) as $model) {
                    if ((int) ($model['model_id'] ?? 0) === $modelId) {
                        $modelSkuLabel = (string) ($model['model_sku'] ?? $modelSkuLabel);
                        break;
                    }
                }
            }

            $rows[] = [
                'item_id' => $itemId,
                'model_id' => $modelId > 0 ? $modelId : null,
                'item_name' => $name,
                'item_sku' => $modelSkuLabel,
                'parent_item_sku' => $parentSku,
            ];
        }

        return $rows;
    }

    public function looksLikeVariationCode(string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '' || ctype_digit($keyword)) {
            return false;
        }

        // Kode Variasi / Aria SKU shapes (TYPE-PCODE-COLOR), not plain words like "knee".
        return (bool) preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+$/', $keyword);
    }

    /**
     * When name search finds nothing, scan one catalog page and match `model_sku` exactly.
     *
     * @return array{
     *     matches: list<array{item_id: int, model_id: int}>,
     *     api_calls: int,
     *     next_offset: int|null
     * }
     */
    public function findExactModelSkuOnCatalogPage(
        string $modelSku,
        int $offset = 0,
        int $pageSize = 30,
        int $maxModelFetches = 8,
    ): array {
        $needle = strtoupper(trim($modelSku));
        $apiCalls = 0;

        if ($needle === '') {
            return ['matches' => [], 'api_calls' => 0, 'next_offset' => null];
        }

        $page = $this->fetchCatalogItemIds($offset, $pageSize);
        $apiCalls++;

        $matches = [];
        $fetched = 0;

        foreach ($page['item_ids'] as $itemId) {
            if ($fetched >= $maxModelFetches) {
                break;
            }

            $models = $this->modelsForItem($itemId);
            $apiCalls++;
            $fetched++;

            foreach ($models as $model) {
                if (strtoupper(trim((string) ($model['model_sku'] ?? ''))) !== $needle) {
                    continue;
                }

                $matches[] = [
                    'item_id' => $itemId,
                    'model_id' => (int) ($model['model_id'] ?? 0),
                ];
            }
        }

        return [
            'matches' => $this->uniqueItemModelMatches($matches),
            'api_calls' => $apiCalls,
            'next_offset' => $page['next_offset'],
        ];
    }

    /**
     * @return list<string>
     */
    public function itemNameSearchQueries(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $queries = [$keyword];
        $parts = array_values(array_filter(explode('-', $keyword), fn (string $p) => $p !== ''));

        if (count($parts) >= 3) {
            $queries[] = implode('-', array_slice($parts, 1, -1));
        }

        if (count($parts) >= 2) {
            $queries[] = implode('-', array_slice($parts, -2));
        }

        if (count($parts) >= 1) {
            $last = $parts[count($parts) - 1];
            if (strlen($last) >= 4) {
                $queries[] = $last;
            }
        }

        $unique = [];
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '' || strlen($query) < 3) {
                continue;
            }
            $key = strtoupper($query);
            if (! isset($unique[$key])) {
                $unique[$key] = $query;
            }
        }

        return array_slice(array_values($unique), 0, self::MAX_NAME_SEARCH_QUERIES);
    }

    /**
     * Shopee v2.product.search_item is GET (query params), not POST JSON.
     *
     * @param  list<string>  $itemStatus
     * @return list<int>
     */
    private function searchItemIdsByName(string $itemName, int $pageSize, array $itemStatus): array
    {
        $itemName = trim($itemName);
        if ($itemName === '') {
            return [];
        }

        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiGet('/api/v2/product/search_item', [
                'page_size' => min(50, max(1, $pageSize)),
                'item_name' => $itemName,
            ], [
                'item_status' => array_values($itemStatus),
            ]),
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
     * @return array{item_ids: list<int>, next_offset: int|null}
     */
    private function fetchCatalogItemIds(int $offset, int $pageSize): array
    {
        $pageSize = min(100, max(1, $pageSize));

        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiGet('/api/v2/product/get_item_list', [
                'offset' => max(0, $offset),
                'page_size' => $pageSize,
            ], [
                'item_status' => ['NORMAL', 'UNLIST'],
            ]),
            'Shopee item list',
        );

        if ($data === null) {
            return ['item_ids' => [], 'next_offset' => null];
        }

        $response = $data['response'] ?? $data;
        $items = $response['item'] ?? [];

        $ids = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = (int) ($item['item_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $hasNext = (bool) ($response['has_next_page'] ?? false);
        $nextOffset = $hasNext ? (int) ($response['next_offset'] ?? ($offset + $pageSize)) : null;

        return [
            'item_ids' => $ids,
            'next_offset' => $nextOffset,
        ];
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
     * @param  list<array{item_id: int, model_id: int}>  $matches
     * @return list<array{item_id: int, model_id: int}>
     */
    private function uniqueItemModelMatches(array $matches): array
    {
        $unique = [];
        foreach ($matches as $match) {
            $key = $match['item_id'].'-'.$match['model_id'];
            $unique[$key] = $match;
        }

        return array_values($unique);
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
            $this->openApi->shopApiGet('/api/v2/product/get_model_list', [
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
