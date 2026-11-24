<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step marketplace AWB: request (ready at platform) vs download (PDF + awb_printed_at).
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
    public function markDocumentRequested(string $channel, array $orderRefs): void
    {
        if (! $this->requestColumnEnabled()) {
            return;
        }

        $channel = strtolower(trim($channel));
        if ($channel === '') {
            return;
        }

        $now = now();
        foreach ($this->normalizeOrderRefs($orderRefs) as $orderRef) {
            MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->update(['awb_document_requested_at' => $now]);
        }
    }

    /**
     * @param  list<string>  $orderRefs
     */
    public function markDocumentReady(string $channel, array $orderRefs): void
    {
        if (! $this->columnsEnabled()) {
            return;
        }

        $channel = strtolower(trim($channel));
        if ($channel === '') {
            return;
        }

        $now = now();
        foreach ($this->normalizeOrderRefs($orderRefs) as $orderRef) {
            MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->update(['awb_document_ready_at' => $now]);
        }
    }

    /**
     * @param  list<string>  $orderRefs
     * @return array{allowed: list<string>, blocked: list<string>}
     */
    public function partitionOrdersForAwbDownload(string $channel, array $orderRefs): array
    {
        $orderRefs = $this->normalizeOrderRefs($orderRefs);
        $channel = strtolower(trim($channel));

        if ($orderRefs === [] || ! $this->columnsEnabled() || $channel === '') {
            return ['allowed' => $orderRefs, 'blocked' => []];
        }

        $readyRefs = MarketplaceChannelOrder::query()
            ->where('channel', $channel)
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