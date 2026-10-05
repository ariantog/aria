<?php

namespace App\Services\Shopee;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class ShopeeStockApiService
{
    /** Max `search_item` calls when resolving one keyword (name fragments only). */
    private const MAX_NAME_SEARCH_QUERIES = 4;

    /** Concurrent Shopee get_model_list requests per pool wave. */
    private const MODEL_LIST_POOL_SIZE = 20;

    /**
     * @var array<int, list<array<string, mixed>>>
     */
    private array $modelsForItemCache = [];

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
     * Flatten listing hits into one row per Shopee variation (model_sku / Kode Variasi).
     *
     * @param  list<array<string, mixed>>  $itemSummaries
     * @return list<array<string, mixed>>
     */
    public function expandSearchResultsWithModels(array $itemSummaries, string $ariaSku, int $maxItems = 12): array
    {
        $needle = strtoupper(trim($ariaSku));
        $rows = [];

        $summariesToExpand = [];
        foreach ($itemSummaries as $summary) {
            if (count($summariesToExpand) >= $maxItems) {
                break;
            }

            $itemId = (int) ($summary['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $summariesToExpand[] = $summary;
        }

        $itemIds = array_values(array_unique(array_map(
            fn (array $summary) => (int) ($summary['item_id'] ?? 0),
            $summariesToExpand,
        )));
        $modelsByItem = $this->modelsByItemIds($itemIds);

        foreach ($summariesToExpand as $summary) {
            $itemId = (int) ($summary['item_id'] ?? 0);
            $models = $modelsByItem[$itemId] ?? [];
            $itemName = (string) ($summary['item_name'] ?? '');
            $parentSku = (string) ($summary['item_sku'] ?? '');

            if ($models === []) {
                $rows[] = [
                    'item_id' => $itemId,
                    'model_id' => null,
                    'item_name' => $itemName,
                    'item_sku' => $parentSku,
                    'parent_item_sku' => $parentSku,
                    'model_sku' => '',
                    'exact_match' => $needle !== '' && strtoupper(trim($parentSku)) === $needle,
                ];

                continue;
            }

            foreach ($models as $model) {
                $modelSku = (string) ($model['model_sku'] ?? '');
                $rows[] = [
                    'item_id' => $itemId,
                    'model_id' => (int) ($model['model_id'] ?? 0),
                    'item_name' => $itemName,
                    'item_sku' => $modelSku !== '' ? $modelSku : $parentSku,
                    'parent_item_sku' => $parentSku,
                    'model_sku' => $modelSku,
                    'exact_match' => $needle !== '' && strtoupper(trim($modelSku)) === $needle,
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            $ae = ! empty($a['exact_match']);
            $be = ! empty($b['exact_match']);
            if ($ae !== $be) {
                return $be <=> $ae;
            }

            return strcmp((string) ($a['model_sku'] ?? ''), (string) ($b['model_sku'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function mergeSearchResultRows(array $rows): array
    {
        $unique = [];
        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $modelId = (int) ($row['model_id'] ?? 0);
            $key = $itemId.'-'.$modelId;
            if (! isset($unique[$key])) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
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

        $itemIdsToFetch = array_slice($page['item_ids'], 0, max(1, $maxModelFetches));
        $modelsByItem = $this->modelsByItemIds($itemIdsToFetch);
        $apiCalls += count($itemIdsToFetch);

        $matches = [];
        foreach ($itemIdsToFetch as $itemId) {
            $models = $modelsByItem[$itemId] ?? [];

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
            $queries[] = $parts[0].'-'.$parts[1];
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

        if (array_key_exists($itemId, $this->modelsForItemCache)) {
            return $this->modelsForItemCache[$itemId];
        }

        $models = $this->fetchModelsForItem($itemId);
        $this->modelsForItemCache[$itemId] = $models;

        return $models;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function modelsByItemIds(array $itemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $out = [];
        $missing = [];

        foreach ($ids as $itemId) {
            if (array_key_exists($itemId, $this->modelsForItemCache)) {
                $cached = $this->modelsForItemCache[$itemId];
                if ($cached !== []) {
                    $out[$itemId] = $cached;
                }

                continue;
            }

            $missing[] = $itemId;
        }

        foreach (array_chunk($missing, self::MODEL_LIST_POOL_SIZE) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $itemId) {
                    $url = $this->openApi->signedShopGetUrl('/api/v2/product/get_model_list', [
                        'item_id' => $itemId,
                    ]);
                    if ($url !== null) {
                        $pool->as((string) $itemId)->timeout(30)->get($url);
                    }
                }
            });

            foreach ($chunk as $itemId) {
                $key = (string) $itemId;
                $response = $responses[$key] ?? null;
                $models = $response !== null
                    ? $this->parseModelListResponse($response)
                    : [];
                $this->modelsForItemCache[$itemId] = $models;
                if ($models !== []) {
                    $out[$itemId] = $models;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchModelsForItem(int $itemId): array
    {
        $data = $this->openApi->decodeShopResponse(
            $this->openApi->shopApiGet('/api/v2/product/get_model_list', [
                'item_id' => $itemId,
            ]),
            'Shopee model list',
        );

        return $this->modelsFromDecodedPayload($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseModelListResponse(\Illuminate\Http\Client\Response $response): array
    {
        $data = $this->openApi->decodeShopResponse($response, 'Shopee model list');

        return $this->modelsFromDecodedPayload($data);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<array<string, mixed>>
     */
    private function modelsFromDecodedPayload(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        $response = $data['response'] ?? $data;
        $models = $response['model'] ?? $response['model_list'] ?? [];

        return is_array($models) ? array_values(array_filter($models, 'is_array')) : [];
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
