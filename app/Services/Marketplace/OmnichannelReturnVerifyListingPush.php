<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4: after physical verify — optional auto-post RETURN, then multi-channel listing push.
 */
class OmnichannelReturnVerifyListingPush
{
    public function __construct(
        private OmnichannelPostReturnEligibility $eligibility,
        private OmnichannelReturnOrderPoster $poster,
        private OmnichannelPostSellLineResolver $lineResolver,
        private MarketplaceListingStockPusher $pusher,
    ) {}

    public function verifyPushEnabled(): bool
    {
        return (bool) config('marketplace.listing_push.return_verify_listing_push', false);
    }

    public function verifyAutoPostEnabled(): bool
    {
        return (bool) config('marketplace.listing_push.return_verify_auto_post', true);
    }

    /**
     * @return array{posted: bool, post_error: ?string, listing: array{pushed: int, skipped: int, errors: list<string>}}
     */
    public function afterVerify(MarketplaceChannelOrder $order, User $user): array
    {
        $result = [
            'posted' => false,
            'post_error' => null,
            'listing' => ['pushed' => 0, 'skipped' => 0, 'errors' => []],
        ];

        if (! $this->verifyPushEnabled()) {
            return $result;
        }

        $order->refresh();

        $alreadyPosted = Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')
            && (int) ($order->posted_return_transaction_id ?? 0) > 0;

        if ($this->verifyAutoPostEnabled()
            && $this->eligibility->masterEnabled()
            && ! $alreadyPosted) {
            $post = $this->poster->postOrder($order, false);
            if ($post['posted'] ?? false) {
                $result['posted'] = true;
                $order->refresh();
            } elseif (! ($post['skipped'] ?? false) && filled($post['error'] ?? null)) {
                $result['post_error'] = (string) $post['error'];
            }
        }

        $warehouseId = (int) ($order->aria_warehouse_id ?? 0);
        if ($warehouseId <= 0) {
            $result['listing']['errors'][] = 'aria_warehouse_id kosong — tidak bisa push listing.';

            return $result;
        }

        $lines = $this->lineResolver->linesForOrder($order);
        if ($lines === []) {
            $result['listing']['errors'][] = 'Line return tidak ditemukan di shadow/reservasi.';

            return $result;
        }

        $result['listing'] = $this->pusher->pushAllChannelsForReturnLines(
            $lines,
            $warehouseId,
            (string) $order->order_ref,
        );

        return $result;
    }
}