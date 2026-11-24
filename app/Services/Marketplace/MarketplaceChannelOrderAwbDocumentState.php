<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step Shopee AWB: request (task READY at Shopee) vs download (PDF + awb_printed_at).
 */
class MarketplaceChannelOrderAwbDocumentState
{
    public function columnsEnabled(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'awb_document_ready_at');
    }

    /**
     * @param  list<string>  $orderRefs
     */
    public function markDocumentRequested(array $orderRefs): void
    {
        if (! $this->requestColumnEnabled()) {
            return;
        }

        $now = now();
        foreach ($this->normalizeOrderRefs($orderRefs) as $orderRef) {
            MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
                ->where('order_ref', $orderRef)
                ->update(['awb_document_requested_at' => $now]);
        }
    }

    /**
     * @param  list<string>  $orderRefs
     */
    public function markDocumentReady(array $orderRefs): void
    {
        if (! $this->columnsEnabled()) {
            return;
        }

        $now = now();
        foreach ($this->normalizeOrderRefs($orderRefs) as $orderRef) {
            MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
                ->where('order_ref', $orderRef)
                ->update(['awb_document_ready_at' => $now]);
        }
    }

    /**
     * @param  list<string>  $orderRefs
     * @return array{allowed: list<string>, blocked: list<string>}
     */
    public function partitionOrdersForAwbDownload(array $orderRefs): array
    {
        $orderRefs = $this->normalizeOrderRefs($orderRefs);

        if ($orderRefs === [] || ! $this->columnsEnabled()) {
            return ['allowed' => $orderRefs, 'blocked' => []];
        }

        $readyRefs = MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->whereIn('order_ref', $orderRefs)
            ->whereNotNull('awb_document_ready_at')
            ->pluck('order_ref')
            ->all();

        $readySet = array_fill_keys($readyRefs, true);
        $allowed = [];
        $blocked = [];

        foreach ($orderRefs as $ref) {
            if (isset($readySet[$ref])) {
                $allowed[] = $ref;
            } else {
                $blocked[] = $ref;
            }
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    private function requestColumnEnabled(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'awb_document_requested_at');
    }

    /**
     * @param  list<string>  $orderRefs
     * @return list<string>
     */
    private function normalizeOrderRefs(array $orderRefs): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($ref) => trim((string) $ref),
            $orderRefs,
        ))));
    }
}