<?php

namespace App\Services\TikTokShop;

use App\Models\Item;

class TikTokShopItemSearchService
{
    public function __construct(
        private TikTokShopOpenApiService $openApi,
    ) {}

    public function isReady(): bool
    {
        return $this->openApi->partnerListReady();
    }

    public function resolveShopCipher(?string $override = null): string
    {
        $override = trim((string) $override);
        if ($override !== '') {
            return $override;
        }

        $configured = trim((string) config('tiktok_shop.shop_cipher', ''));
        if ($configured !== '') {
            return $configured;
        }

        $shops = $this->openApi->listAuthorizedShops();
        $first = $shops[0]['cipher'] ?? '';

        return trim((string) $first);
    }

    /**
     * @return list<array{
     *     product_id: string,
     *     sku_id: string,
     *     product_name: string,
     *     seller_sku: string,
     *     exact_match: bool,
     * }>
     */
    public function searchForItem(Item $item, string $query, ?string $shopCipher = null): array
    {
        $shopCipher = $this->resolveShopCipher($shopCipher);
        if ($shopCipher === '') {
            return [];
        }

        $ariaSku = strtoupper(trim((string) $item->code));
        $legacy = strtoupper(trim((string) ($item->legacy_code ?? '')));
        $query = trim($query);

        $sellerSkus = array_values(array_unique(array_filter([
            $query !== '' ? $query : null,
            $ariaSku !== '' ? $ariaSku : null,
            $legacy !== '' && $legacy !== $ariaSku ? $legacy : null,
        ])));

        if ($sellerSkus === []) {
            return [];
        }

        $chunks = array_chunk($sellerSkus, 10);
        $rows = [];

        foreach ($chunks as $chunk) {
            $products = $this->openApi->searchProductsBySellerSkus($shopCipher, $chunk);
            $rows = array_merge($rows, $this->flattenProductsToRows($products, $ariaSku, $legacy));
        }

        return $this->dedupeRows($rows);
    }

    public function formatLastErrorForUser(): ?string
    {
        $err = $this->openApi->getLastOpenApiError();
        if (! is_array($err)) {
            return null;
        }

        $message = trim((string) ($err['message'] ?? ''));
        if ($message === '') {
            return null;
        }

        $code = $err['code'] ?? null;
        if ($code !== null && $code !== '') {
            return $message.' (code: '.$code.')';
        }

        return $message;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array{product_id: string, sku_id: string, product_name: string, seller_sku: string, exact_match: bool}>
     */
    private function flattenProductsToRows(array $products, string $ariaSku, string $legacySku): array
    {
        $rows = [];
        $needles = array_values(array_filter(array_unique([$ariaSku, $legacySku])));

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $productId = (string) ($product['id'] ?? $product['product_id'] ?? '');
            $productName = (string) ($product['title'] ?? $product['product_name'] ?? $product['name'] ?? '');
            $skus = $product['skus'] ?? $product['sku_list'] ?? [];

            if (! is_array($skus)) {
                continue;
            }

            foreach ($skus as $sku) {
                if (! is_array($sku)) {
                    continue;
                }

                $skuId = (string) ($sku['id'] ?? $sku['sku_id'] ?? '');
                $sellerSku = strtoupper(trim((string) ($sku['seller_sku'] ?? $sku['sku_code'] ?? '')));
                if ($productId === '' || $skuId === '') {
                    continue;
                }

                $exact = $sellerSku !== '' && in_array($sellerSku, $needles, true);

                $rows[] = [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'product_name' => $productName,
                    'seller_sku' => $sellerSku,
                    'exact_match' => $exact,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => ($b['exact_match'] <=> $a['exact_match']) ?: strcmp($a['seller_sku'], $b['seller_sku']));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $key = ($row['product_id'] ?? '').'|'.($row['sku_id'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }
}