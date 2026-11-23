<?php

namespace App\Services\Marketplace;

/**
 * Extract SKU + qty lines from TikTok Shop webhook payloads and Get Order Detail JSON.
 */
class TikTokOrderLineSummary
{
    /**
     * @param  array<string, mixed>  $payload  Full webhook or API response
     * @return list<array{sku: string, qty: float}>
     */
    public function fromWebhookPayload(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $candidates = [];
        foreach (['line_items', 'sku_list', 'order_line_list', 'item_list'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $candidates = $data[$key];
                break;
            }
        }

        if ($candidates === []) {
            return [];
        }

        return $this->normalizeRows($candidates);
    }

    /**
     * @param  array<string, mixed>|null  $apiResponse  TikTok getOrderDetail body
     * @return list<array{sku: string, qty: float}>
     */
    public function fromOrderDetailResponse(?array $apiResponse): array
    {
        if ($apiResponse === null) {
            return [];
        }

        $orders = $apiResponse['data']['orders'] ?? null;
        if (! is_array($orders) || $orders === []) {
            $single = $apiResponse['data']['order'] ?? null;
            $orders = is_array($single) ? [$single] : [];
        }

        $first = $orders[0] ?? null;
        if (! is_array($first)) {
            return [];
        }

        $items = $first['line_items'] ?? $first['sku_list'] ?? [];

        return is_array($items) ? $this->normalizeRows($items) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{sku: string, qty: float}>
     */
    protected function normalizeRows(array $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = $row['seller_sku']
                ?? $row['sku']
                ?? $row['sku_code']
                ?? $row['product_sku']
                ?? $row['seller_sku_code']
                ?? '';

            $sku = strtoupper(trim((string) $sku));
            if ($sku === '') {
                continue;
            }

            $qty = $row['quantity']
                ?? $row['sku_quantity']
                ?? $row['product_quantity']
                ?? $row['qty']
                ?? 0;

            $qty = (float) $qty;
            if ($qty <= 0) {
                continue;
            }

            $lines[] = ['sku' => $sku, 'qty' => $qty];
        }

        return $lines;
    }
}