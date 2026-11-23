<?php

namespace App\Services\Marketplace;

use App\Models\Item;

/**
 * Match Shopee item_list SKUs to Aria items (code / legacy_code).
 */
class ShopeeFlexiSellItemMatcher
{
    /**
     * @param  list<array{sku: string, qty: float, amount: float}>  $lines
     * @return array{error: ?string, items: list<array{itemId: int, code: string, name: string, quantity: float, price: float, discount: int, subtotal: float}>}
     */
    public function matchLines(array $lines): array
    {
        if ($lines === []) {
            return [
                'error' => 'Payload order tidak memiliki baris item.',
                'items' => [],
            ];
        }

        $skus = collect($lines)->pluck('sku')->filter()->unique()->values()->all();
        $upperSkus = array_map('strtoupper', $skus);

        $items = Item::query()
            ->where(function ($query) use ($upperSkus) {
                foreach ($upperSkus as $sku) {
                    $query->orWhereRaw('UPPER(code) = ?', [$sku])
                        ->orWhereRaw('UPPER(legacy_code) = ?', [$sku]);
                }
            })
            ->get(['id', 'code', 'name', 'legacy_code']);

        $skuToItem = [];
        foreach ($items as $item) {
            $skuToItem[strtoupper((string) $item->code)] = $item;
            $legacy = trim((string) $item->legacy_code);
            if ($legacy !== '') {
                $skuToItem[strtoupper($legacy)] = $item;
            }
        }

        $missingLabels = [];
        $matched = [];

        foreach ($lines as $line) {
            $sku = strtoupper((string) ($line['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $product = $skuToItem[$sku] ?? null;
            if ($product === null) {
                $missingLabels[] = $sku.' (belum ada di Aria code/legacy_code)';

                continue;
            }

            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $lineTotal = (float) ($line['amount'] ?? 0);
            $price = $qty > 0 ? $lineTotal / $qty : 0.0;

            $matched[] = [
                'itemId' => (int) $product->id,
                'code' => (string) $product->code,
                'name' => (string) $product->name,
                'quantity' => $qty,
                'price' => $price,
                'discount' => 0,
                'subtotal' => $lineTotal,
            ];
        }

        if ($missingLabels !== []) {
            return [
                'error' => 'SKU Shopee tidak ditemukan di Aria: '.implode(', ', $missingLabels),
                'items' => [],
            ];
        }

        if ($matched === []) {
            return [
                'error' => 'Payload order tidak memiliki baris item yang valid.',
                'items' => [],
            ];
        }

        return ['error' => null, 'items' => $matched];
    }
}