<?php

namespace App\Services\Marketplace;

/**
 * Normalize Shopee get_order_detail item_list rows for shadow storage/compare.
 */
class ShopeeOrderLineSummary
{
    /**
     * @param  array<string, mixed>|null  $orderRow  order_list[0] from Shopee API
     * @return array{lines: list<array{sku: string, qty: float, amount: float}>, line_count: int, total_amount: float|null}
     */
    public function fromOrderRow(?array $orderRow): array
    {
        if (! is_array($orderRow)) {
            return [
                'lines' => [],
                'line_count' => 0,
                'total_amount' => null,
            ];
        }

        $itemList = $orderRow['item_list'] ?? [];
        if (! is_array($itemList)) {
            $itemList = [];
        }

        $lines = [];
        foreach ($itemList as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = strtoupper(trim((string) ($row['model_sku'] ?? $row['item_sku'] ?? $row['model_id'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $qty = (float) ($row['model_quantity_purchased'] ?? $row['quantity'] ?? 0);
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $unit = (float) ($row['model_discounted_price'] ?? $row['model_original_price'] ?? 0);
            $lineAmount = isset($row['model_discounted_price']) || isset($row['model_original_price'])
                ? round($unit * $qty, 2)
                : (float) ($row['item_price'] ?? 0);

            $lines[] = [
                'sku' => $sku,
                'qty' => $qty,
                'amount' => $lineAmount,
            ];
        }

        $headerTotal = isset($orderRow['total_amount']) ? (float) $orderRow['total_amount'] : null;

        return [
            'lines' => $lines,
            'line_count' => count($lines),
            'total_amount' => $headerTotal,
        ];
    }
}