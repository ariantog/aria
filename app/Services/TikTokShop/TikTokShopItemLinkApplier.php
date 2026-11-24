<?php

namespace App\Services\TikTokShop;

use App\Models\Item;

/**
 * Persist TikTok Shop / Tokopedia (Partner) product + SKU IDs on an Aria item — link only.
 */
class TikTokShopItemLinkApplier
{
    /**
     * @param  list<array<string, mixed>>|null  $prefetchedSkuRows  Rows with product_id, sku_id, seller_sku
     * @return array{ok: bool, unchanged?: bool, message?: string, tiktok_shop_sku_id?: ?string}
     */
    public function apply(
        Item $item,
        string $productId,
        ?string $skuId = null,
        ?array $prefetchedSkuRows = null,
    ): array {
        $productId = trim($productId);
        if ($productId === '') {
            return ['ok' => false, 'message' => 'TikTok product ID tidak valid.'];
        }

        $skuId = trim((string) ($skuId ?? ''));
        $skus = $prefetchedSkuRows ?? [];

        if ($skuId === '' && $skus !== []) {
            $picked = $this->pickSkuBySellerCode($skus, (string) $item->code, (string) ($item->legacy_code ?? ''));
            if ($picked === null && count($skus) > 1) {
                return [
                    'ok' => false,
                    'message' => 'SKU tidak jelas — pilih baris SKU yang cocok atau isi tiktok_shop_sku_id (seller_sku harus = '.$item->code.').',
                ];
            }
            $skuId = (string) ($picked['sku_id'] ?? ($skus[0]['sku_id'] ?? ''));
        }

        if ($skuId === '') {
            return ['ok' => false, 'message' => 'TikTok SKU ID wajib ketika produk punya beberapa variasi.'];
        }

        if ($skus !== []) {
            $valid = $this->findSkuRow($skus, $productId, $skuId);
            if ($valid === null) {
                return ['ok' => false, 'message' => 'tiktok_shop_sku_id tidak valid untuk product ID ini.'];
            }
        }

        $existingProduct = trim((string) ($item->tiktok_shop_product_id ?? ''));
        $existingSku = trim((string) ($item->tiktok_shop_sku_id ?? ''));

        if ($existingProduct === $productId && $existingSku === $skuId) {
            return ['ok' => true, 'unchanged' => true, 'tiktok_shop_sku_id' => $skuId];
        }

        $this->persistLinkOnly($item, $productId, $skuId);

        return ['ok' => true, 'tiktok_shop_sku_id' => $skuId];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function pickSkuBySellerCode(array $rows, string $code, string $legacyCode): ?array
    {
        $needles = array_values(array_filter(array_unique([
            strtoupper(trim($code)),
            strtoupper(trim($legacyCode)),
        ])));

        if ($needles === []) {
            return null;
        }

        $matches = [];
        foreach ($rows as $row) {
            $sellerSku = strtoupper(trim((string) ($row['seller_sku'] ?? '')));
            if ($sellerSku !== '' && in_array($sellerSku, $needles, true)) {
                $matches[] = $row;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function findSkuRow(array $rows, string $productId, string $skuId): ?array
    {
        foreach ($rows as $row) {
            $rowProduct = (string) ($row['product_id'] ?? '');
            $rowSku = (string) ($row['sku_id'] ?? '');
            if ($rowProduct === $productId && $rowSku === $skuId) {
                return $row;
            }
        }

        return null;
    }

    private function persistLinkOnly(Item $item, string $productId, string $skuId): void
    {
        Item::query()
            ->whereKey($item->getKey())
            ->update([
                'tiktok_shop_product_id' => $productId,
                'tiktok_shop_sku_id' => $skuId,
            ]);

        $item->tiktok_shop_product_id = $productId;
        $item->tiktok_shop_sku_id = $skuId;
    }
}