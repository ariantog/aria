<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceChannelOrderAwbPrint;
use App\Models\Omnichannel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * One AWB print per marketplace order unless the user may reprint; full append-only audit trail.
 */
class MarketplaceChannelOrderAwbPrintGuard
{
    /**
     * @param  list<string>  $orderRefs
     * @return array{allowed: list<string>, blocked: list<string>}
     */
    public function partitionOrdersForAwbPrint(string $channel, array $orderRefs, User $user): array
    {
        $orderRefs = $this->normalizeOrderRefs($orderRefs);
        $channel = strtolower(trim($channel));

        if ($orderRefs === []) {
            return ['allowed' => [], 'blocked' => []];
        }

        if (Omnichannel::userCanReprintAwb($user)) {
            return ['allowed' => $orderRefs, 'blocked' => []];
        }

        if (! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_at')) {
            return ['allowed' => $orderRefs, 'blocked' => []];
        }

        if ($channel === '') {
            return ['allowed' => $orderRefs, 'blocked' => []];
        }

        $printedRefs = MarketplaceChannelOrder::query()
            ->where('channel', $channel)
            ->whereIn('order_ref', $orderRefs)
            ->whereNotNull('awb_printed_at')
            ->pluck('order_ref')
            ->all();

        $printedSet = array_fill_keys($printedRefs, true);

        $allowed = [];
        $blocked = [];

        foreach ($orderRefs as $ref) {
            if (isset($printedSet[$ref])) {
                $blocked[] = $ref;
            } else {
                $allowed[] = $ref;
            }
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    /**
     * @param  list<string>  $orderRefs
     */
    public function recordSuccessfulPrints(string $channel, array $orderRefs, User $user): int
    {
        $userId = (int) $user->id;
        if ($userId <= 0) {
            return 0;
        }

        $orderRefs = $this->normalizeOrderRefs($orderRefs);
        $channel = strtolower(trim($channel));
        if ($orderRefs === [] || $channel === '') {
            return 0;
        }

        $auditEnabled = Schema::hasTable('marketplace_channel_order_awb_prints');
        $summaryEnabled = Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'awb_printed_by');

        if (! $auditEnabled && ! $summaryEnabled) {
            return 0;
        }

        $priorPrintCounts = [];
        if ($auditEnabled) {
            $priorPrintCounts = MarketplaceChannelOrderAwbPrint::query()
                ->selectRaw('order_ref, COUNT(*) as c')
                ->where('channel', $channel)
                ->whereIn('order_ref', $orderRefs)
                ->groupBy('order_ref')
                ->pluck('c', 'order_ref')
                ->all();
        }

        $now = now();
        $recorded = 0;

        foreach ($orderRefs as $orderRef) {
            $isReprint = ((int) ($priorPrintCounts[$orderRef] ?? 0)) > 0;

            if ($auditEnabled) {
                MarketplaceChannelOrderAwbPrint::query()->create([
                    'channel' => $channel,
                    'order_ref' => $orderRef,
                    'user_id' => $userId,
                    'printed_at' => $now,
                    'is_reprint' => $isReprint,
                ]);
            }

            if ($summaryEnabled) {
                MarketplaceChannelOrder::query()
                    ->where('channel', $channel)
                    ->where('order_ref', $orderRef)
                    ->update([
                        'awb_printed_by' => $userId,
                        'awb_printed_at' => $now,
                    ]);
            }

            $recorded++;
        }

        return $recorded;
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