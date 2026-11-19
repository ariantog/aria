<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;

class ShopeeJubelioShadowComparer
{
    /**
     * @return array{compare_status: string, jubelio_invoice: ?string, jubelio_order_status: ?string, jubelio_order_id: ?int, compare_note: ?string}
     */
    public function compare(string $orderSn, ?string $shopeeStatus): array
    {
        $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));
        $baseInvoice = $prefix.'-'.$orderSn;

        $jubelio = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where(function ($query) use ($baseInvoice, $orderSn, $prefix) {
                $query->where('invoice', $baseInvoice)
                    ->orWhere('invoice', 'like', $prefix.'-'.$orderSn.'-%');
            })
            ->orderByDesc('id')
            ->first();

        if ($jubelio === null) {
            return [
                'compare_status' => MarketplaceOrderShadow::COMPARE_JUBELIO_MISSING,
                'jubelio_invoice' => null,
                'jubelio_order_status' => null,
                'jubelio_order_id' => null,
                'compare_note' => 'Belum ada baris jubelioorders untuk invoice '.$baseInvoice,
            ];
        }

        $jubStatus = strtoupper(trim((string) $jubelio->order_status));
        $shopStatus = strtoupper(trim((string) ($shopeeStatus ?? '')));

        if ($shopStatus !== '' && $jubStatus !== '' && $shopStatus !== $jubStatus) {
            return [
                'compare_status' => MarketplaceOrderShadow::COMPARE_MISMATCH,
                'jubelio_invoice' => $jubelio->invoice,
                'jubelio_order_status' => $jubelio->order_status,
                'jubelio_order_id' => (int) $jubelio->id,
                'compare_note' => 'Status Shopee '.$shopStatus.' vs Jubelio '.$jubStatus,
            ];
        }

        return [
            'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
            'jubelio_invoice' => $jubelio->invoice,
            'jubelio_order_status' => $jubelio->order_status,
            'jubelio_order_id' => (int) $jubelio->id,
            'compare_note' => null,
        ];
    }
}