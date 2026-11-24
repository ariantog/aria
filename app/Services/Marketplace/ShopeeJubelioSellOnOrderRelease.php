<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOnOrderReservation;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;

/**
 * Shopee Jubelio-ingested SELL: release warehouse_item.on_order only when ingress opted into on_order pairing.
 *
 * Legacy queue placeholders (`shopee-push:`) and bare `SP-` invoices do not release — many rows never reserved on_order.
 * When {@see ShopeeJubelioQueuePrecreator::PLACEHOLDER_ON_ORDER_PREFIX} is used, or the webhook replaced the placeholder
 * but `marketplace_on_order_reservations` exist for the order ref, release on physical deduct.
 */
class ShopeeJubelioSellOnOrderRelease
{
    public function __construct(
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

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

        if (Schema::hasTable('jubelioorders')) {
            $queue = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $invoice)
                ->first();

            if ($queue !== null) {
                if (ShopeeJubelioQueuePrecreator::isOnOrderPlaceholderJubelioOrderId($queue->jubelio_order_id)) {
                    return true;
                }

                if (ShopeeJubelioQueuePrecreator::isLegacyPlaceholderJubelioOrderId($queue->jubelio_order_id)) {
                    return false;
                }

                if ((int) $queue->source === Jubelioorder::SOURCE_SHOPEE_PUSH) {
                    return $this->hasOnOrderReservationForInvoice($invoice);
                }

                return false;
            }
        }

        return $this->hasOnOrderReservationForInvoice($invoice);
    }

    /**
     * @deprecated Release is no longer inferred from invoice prefix alone; kept for tests / diagnostics.
     */
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

    private function hasOnOrderReservationForInvoice(string $invoice): bool
    {
        if (! Schema::hasTable('marketplace_on_order_reservations')) {
            return false;
        }

        $orderRef = $this->orderRefFromShopeeInvoice($invoice);
        if ($orderRef === null) {
            return false;
        }

        return MarketplaceOnOrderReservation::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderRef)
            ->exists();
    }

    private function orderRefFromShopeeInvoice(string $invoice): ?string
    {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return null;
        }

        foreach ($this->shopeeInvoicePrefix->allKnownPrefixes() as $prefix) {
            $quoted = preg_quote($prefix, '/');
            if (preg_match('/^'.$quoted.'-(.+)$/i', $invoice, $matches) === 1) {
                $orderSn = trim($matches[1]);

                return $orderSn !== '' ? $orderSn : null;
            }
        }

        return null;
    }
}