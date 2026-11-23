<?php

namespace App\Services\Marketplace;

/**
 * Best-effort Shopee fulfilment location from push/poll payload or get_order_detail row.
 */
class ShopeeOrderPickupLocation
{
    /**
     * @param  array<string, mixed>  $pushData
     * @param  array<string, mixed>|null  $orderRow  get_order_detail order_list[0]
     * @return array{shopee_location_id: string, shopee_warehouse_id: int}|null
     */
    public function extract(array $pushData, ?array $orderRow = null): ?array
    {
        $fromRow = $orderRow !== null ? $this->fromOrderRow($orderRow) : null;
        if ($fromRow !== null) {
            return $fromRow;
        }

        return $this->fromPushData($pushData);
    }

    /**
     * @param  array<string, mixed>  $pushData
     */
    private function fromPushData(array $pushData): ?array
    {
        $itemList = $pushData['item_list'] ?? null;
        if (! is_array($itemList)) {
            return null;
        }

        return $this->fromItemList($itemList);
    }

    /**
     * @param  array<string, mixed>  $orderRow
     */
    private function fromOrderRow(array $orderRow): ?array
    {
        $fromItems = $this->fromItemList($orderRow['item_list'] ?? []);
        if ($fromItems !== null) {
            return $fromItems;
        }

        $packages = $orderRow['package_list'] ?? [];
        if (! is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            $pkgItems = $package['item_list'] ?? [];
            if (! is_array($pkgItems)) {
                continue;
            }
            foreach ($pkgItems as $pkgItem) {
                if (! is_array($pkgItem)) {
                    continue;
                }
                $loc = $pkgItem['product_location_id'] ?? null;
                $resolved = $this->normalizeLocation($loc);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * @param  mixed  $itemList
     */
    private function fromItemList(mixed $itemList): ?array
    {
        if (! is_array($itemList) || $itemList === []) {
            return null;
        }

        foreach ($itemList as $item) {
            if (! is_array($item)) {
                continue;
            }
            $resolved = $this->normalizeLocation($item['product_location_id'] ?? null);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array{shopee_location_id: string, shopee_warehouse_id: int}|null
     */
    private function normalizeLocation(mixed $loc): ?array
    {
        if (is_array($loc)) {
            foreach ($loc as $piece) {
                $resolved = $this->normalizeLocation($piece);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (is_numeric($loc) && (int) $loc > 0) {
            return [
                'shopee_location_id' => '',
                'shopee_warehouse_id' => (int) $loc,
            ];
        }

        if (! is_string($loc)) {
            return null;
        }

        $loc = trim($loc);
        if ($loc === '') {
            return null;
        }

        if (ctype_digit($loc)) {
            return [
                'shopee_location_id' => '',
                'shopee_warehouse_id' => (int) $loc,
            ];
        }

        return [
            'shopee_location_id' => strtoupper($loc),
            'shopee_warehouse_id' => 0,
        ];
    }
}