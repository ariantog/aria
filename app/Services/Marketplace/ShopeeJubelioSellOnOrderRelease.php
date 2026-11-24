<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;

/**
 * Shopee-only: release warehouse_item.on_order when a Jubelio-ingested SELL deducts stock.
 */
class ShopeeJubelioSellOnOrderRelease
{
    public function shouldReleaseForTransaction(Transaction $transaction): bool
    {
        if ((int) $transaction->type !== Transaction::TYPE_SELL) {
            return false;
        }

        if ((int) $transaction->submit_type !== Transaction::SUBMIT_TYPE_JUBELIO) {
            return false;
        }

        $invoice = trim((string) ($transaction->invoice ?? ''));
        if ($invoice === '') {
            return false;
        }

        if ($this->invoiceLooksLikeShopeeMarketplace($invoice)) {
            return true;
        }

        if (! Schema::hasTable('jubelioorders')) {
            return false;
        }

        $queue = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->first();

        if ($queue === null) {
            return false;
        }

        return (int) $queue->source === Jubelioorder::SOURCE_SHOPEE_PUSH;
    }

    public function invoiceLooksLikeShopeeMarketplace(string $invoice): bool
    {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return false;
        }

        $domestic = strtoupper((string) config('shopee_flexi.domestic_prefix', 'SP'));
        if (str_starts_with(strtoupper($invoice), $domestic.'-')) {
            return true;
        }

        foreach (array_keys(config('shopee_flexi.flexi_prefixes', [])) as $prefix) {
            $prefix = strtoupper(trim((string) $prefix));
            if ($prefix !== '' && str_starts_with(strtoupper($invoice), $prefix.'-')) {
                return true;
            }
        }

        return false;
    }
}