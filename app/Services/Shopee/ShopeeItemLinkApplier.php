<?php

namespace App\Services\Shopee;

use App\Models\Item;

/**
 * Persist Shopee marketplace IDs on an Aria item — link only.
 *
 * Writes {@see Item::$shopee_item_id} and {@see Item::$shopee_model_id} only;
 * does not change SKU, catalog, price, stock, or other item fields.
 */
class ShopeeItemLinkApplier
{
    public function __construct(
        private ShopeeStockApiService $stockApi,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $prefetchedModels
     * @return array{ok: bool, message?: string, shopee_model_id?: ?int}
     */
    public function apply(Item $item, int $shopeeItemId, ?int $explicitModelId = null, ?array $prefetchedModels = null): array
    {
        if ($shopeeItemId <= 0) {
            return ['ok' => false, 'message' => 'Shopee item ID tidak valid.'];
        }

        $models = $prefetchedModels;
        if ($models === null) {
            $models = $this->stockApi->isReady()
                ? $this->stockApi->modelsForItem($shopeeItemId)
                : [];
        }

        $modelId = (int) ($explicitModelId ?? 0);

        if ($modelId <= 0 && $models !== []) {
            $picked = ShopeeModelStock::pickModelBySku($models, (string) $item->code);
            if ($picked === null && count($models) > 1) {
                return [
                    'ok' => false,
                    'message' => 'Variasi tidak jelas — isi shopee_model_id atau pastikan Kode Variasi = '.$item->code,
                ];
            }
            $modelId = (int) ($picked['model_id'] ?? ($models[0]['model_id'] ?? 0));
        }

        if ($models !== [] && $modelId > 0) {
            $picked = ShopeeModelStock::pickModel($models, $modelId, (string) $item->code);
            if ($picked === null) {
                return ['ok' => false, 'message' => 'shopee_model_id tidak valid untuk item Shopee ini.'];
            }
        }

        $storedModelId = $modelId > 0 ? $modelId : null;
        $existingItemId = (int) ($item->shopee_item_id ?? 0);
        $existingModelId = (int) ($item->shopee_model_id ?? 0);
        $newModelId = (int) ($storedModelId ?? 0);

        if ($existingItemId === $shopeeItemId && $existingModelId === $newModelId) {
            return ['ok' => true, 'shopee_model_id' => $storedModelId];
        }

        $this->persistLinkOnly($item, $shopeeItemId, $storedModelId);

        return ['ok' => true, 'shopee_model_id' => $storedModelId];
    }

    private function persistLinkOnly(Item $item, int $shopeeItemId, ?int $shopeeModelId): void
    {
        Item::query()
            ->whereKey($item->getKey())
            ->update([
                'shopee_item_id' => $shopeeItemId,
                'shopee_model_id' => $shopeeModelId,
            ]);

        $item->shopee_item_id = $shopeeItemId;
        $item->shopee_model_id = $shopeeModelId;
    }
}
