<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;

/**
 * Maps in-scope channel orders to dashboard counter buckets (posted / eligible / errors).
 */
class OmnichannelPostSellStatsClassifier
{
    public const BUCKET_POSTED = 'posted';

    public const BUCKET_ELIGIBLE = 'eligible';

    public const BUCKET_ERROR = 'error';

    public function __construct(
        private OmnichannelPostSellStatsScope $scope,
        private OmnichannelPostSellEligibility $eligibility,
    ) {}

    /**
     * @return self::BUCKET_*|null null = out of scope or ignored (e.g. duplicate invoice pending link)
     */
    public function classify(MarketplaceChannelOrder $order): ?string
    {
        if (! $this->scope->orderInScope($order)) {
            return null;
        }

        if ((int) ($order->posted_transaction_id ?? 0) > 0) {
            return self::BUCKET_POSTED;
        }

        $reason = $this->eligibility->rejectReason($order);
        if ($reason === null) {
            return self::BUCKET_ELIGIBLE;
        }

        if ($reason === 'Transaction sudah ada.') {
            return null;
        }

        return self::BUCKET_ERROR;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function classifyAttributes(array $attributes): ?string
    {
        $order = new MarketplaceChannelOrder;
        $order->forceFill($attributes);
        if (isset($attributes['id'])) {
            $order->exists = true;
        }

        return $this->classify($order);
    }
}