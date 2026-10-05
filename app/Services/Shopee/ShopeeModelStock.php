<?php

namespace App\Services\Shopee;

/**
 * Normalizes Shopee product model payloads into sellable/reserved counts.
 */
class ShopeeModelStock
{
    /**
     * @param  array<string, mixed>|null  $modelRow
     */
    public static function sellableQuantity(?array $modelRow, ?string $locationId = null): ?int
    {
        if ($modelRow === null) {
            return null;
        }

        $stockInfoV2 = $modelRow['stock_info_v2'] ?? null;
        if (is_array($stockInfoV2)) {
            $sellerStock = $stockInfoV2['seller_stock'] ?? [];
            if (! is_array($sellerStock)) {
                return null;
            }

            if ($locationId !== null && $locationId !== '') {
                foreach ($sellerStock as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    if ((string) ($row['location_id'] ?? '') === $locationId) {
                        return (int) ($row['stock'] ?? 0);
                    }
                }

                return 0;
            }

            $total = 0;
            foreach ($sellerStock as $row) {
                if (is_array($row)) {
                    $total += (int) ($row['stock'] ?? 0);
                }
            }

            return $total;
        }

        $summary = $modelRow['stock_info'] ?? null;
        if (is_array($summary)) {
            if (isset($summary['current_stock'])) {
                return (int) $summary['current_stock'];
            }
            if (isset($summary[0]['current_stock'])) {
                return (int) $summary[0]['current_stock'];
            }
        }

        if (isset($modelRow['stock'])) {
            return (int) $modelRow['stock'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $modelRow
     */
    public static function reservedQuantity(?array $modelRow): ?int
    {
        if ($modelRow === null) {
            return null;
        }

        $stockInfoV2 = $modelRow['stock_info_v2'] ?? null;
        if (! is_array($stockInfoV2)) {
            return null;
        }

        $summary = $stockInfoV2['summary_info'] ?? null;
        if (! is_array($summary)) {
            return null;
        }

        return (int) ($summary['total_reserved_stock'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return array<string, mixed>|null
     */
    public static function pickModel(array $models, int $modelId, ?string $modelSku = null): ?array
    {
        if ($models === []) {
            return null;
        }

        if ($modelId > 0) {
            foreach ($models as $model) {
                if ((int) ($model['model_id'] ?? 0) === $modelId) {
                    return $model;
                }
            }
        }

        if ($modelSku !== null && $modelSku !== '') {
            $matched = self::pickModelBySku($models, $modelSku);
            if ($matched !== null) {
                return $matched;
            }
        }

        return count($models) === 1 ? ($models[0] ?? null) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return array<string, mixed>|null
     */
    public static function pickModelBySku(array $models, string $modelSku): ?array
    {
        $needle = strtoupper(trim($modelSku));
        if ($needle === '') {
            return null;
        }

        foreach ($models as $model) {
            if (strtoupper(trim((string) ($model['model_sku'] ?? ''))) === $needle) {
                return $model;
            }
        }

        return null;
    }
}
