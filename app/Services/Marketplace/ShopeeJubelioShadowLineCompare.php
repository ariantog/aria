<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\MarketplaceOrderShadow;
use Illuminate\Support\Collection;

class ShopeeJubelioShadowLineCompare
{
    /**
     * @param  array{lines: list<array{sku: string, qty: float, amount: float}>, line_count: int, total_amount: float|null}  $shopee
     * @param  array{lines: list<array{jubelio_item_id: int, aria_item_id: ?int, code: ?string, qty: float, amount: float}>, line_count: int, unlinked: list<string>}  $jubelio
     * @return array{line_compare_status: string, line_compare_note: ?string, flags: list<string>}
     */
    public function compare(array $shopee, array $jubelio): array
    {
        if ($shopee['line_count'] === 0 && $jubelio['line_count'] === 0) {
            return [
                'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_UNAVAILABLE,
                'line_compare_note' => 'Tidak ada baris item di Shopee maupun Jubelio',
                'flags' => [],
            ];
        }

        if ($jubelio['unlinked'] !== []) {
            return [
                'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_UNAVAILABLE,
                'line_compare_note' => 'Item Jubelio belum terhubung: '.implode(', ', array_slice($jubelio['unlinked'], 0, 3)),
                'flags' => ['unlinked'],
            ];
        }

        $shopeeByItem = $this->aggregateShopeeByAriaItem($shopee['lines']);
        if ($shopeeByItem->isEmpty()) {
            return [
                'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_UNAVAILABLE,
                'line_compare_note' => 'SKU Shopee belum cocok dengan katalog Aria',
                'flags' => ['shopee_sku_unmapped'],
            ];
        }

        /** @var array<int, float> $jubelioByItem */
        $jubelioByItem = [];
        foreach ($jubelio['lines'] as $line) {
            $ariaId = (int) ($line['aria_item_id'] ?? 0);
            if ($ariaId <= 0) {
                continue;
            }
            $jubelioByItem[$ariaId] = ($jubelioByItem[$ariaId] ?? 0) + (float) $line['qty'];
        }

        $flags = [];
        $notes = [];

        $ariaIds = collect(array_keys($jubelioByItem))->merge($shopeeByItem->keys())->unique()->values();
        if ($ariaIds->count() !== count($jubelioByItem) || $ariaIds->count() !== $shopeeByItem->count()) {
            $flags[] = 'line_count';
            $notes[] = 'Jumlah SKU berbeda (Shopee '.$shopeeByItem->count().' vs Jubelio '.count($jubelioByItem).')';
        }

        foreach ($ariaIds as $ariaItemId) {
            $shopQty = (float) ($shopeeByItem->get($ariaItemId) ?? 0);
            $jubQty = (float) ($jubelioByItem[(int) $ariaItemId] ?? 0);
            if (abs($shopQty - $jubQty) > 0.0001) {
                $flags[] = 'qty';
                $item = Item::query()->find($ariaItemId);
                $code = $item?->code ?? ('#'.$ariaItemId);
                $notes[] = $code.' qty Shopee '.$shopQty.' vs Jubelio '.$jubQty;
            }
        }

        if ($flags !== []) {
            return [
                'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_MISMATCH,
                'line_compare_note' => implode('; ', array_slice($notes, 0, 4)),
                'flags' => array_values(array_unique($flags)),
            ];
        }

        return [
            'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_MATCHED,
            'line_compare_note' => null,
            'flags' => [],
        ];
    }

    /**
     * @param  list<array{sku: string, qty: float, amount: float}>  $lines
     * @return Collection<int, float>
     */
    private function aggregateShopeeByAriaItem(array $lines): Collection
    {
        $skus = collect($lines)->pluck('sku')->filter()->unique()->values()->all();
        if ($skus === []) {
            return collect();
        }

        $upperSkus = array_map('strtoupper', $skus);

        $items = Item::query()
            ->where(function ($query) use ($upperSkus) {
                foreach ($upperSkus as $sku) {
                    $query->orWhereRaw('UPPER(code) = ?', [$sku])
                        ->orWhereRaw('UPPER(legacy_code) = ?', [$sku]);
                }
            })
            ->get(['id', 'code', 'legacy_code']);

        $skuToItemId = [];
        foreach ($items as $item) {
            $skuToItemId[strtoupper((string) $item->code)] = (int) $item->id;
            $legacy = trim((string) $item->legacy_code);
            if ($legacy !== '') {
                $skuToItemId[strtoupper($legacy)] = (int) $item->id;
            }
        }

        $totals = [];
        foreach ($lines as $line) {
            $sku = strtoupper((string) $line['sku']);
            $itemId = $skuToItemId[$sku] ?? null;
            if ($itemId === null) {
                continue;
            }
            $totals[$itemId] = ($totals[$itemId] ?? 0) + (float) $line['qty'];
        }

        return collect($totals);
    }
}