<?php

namespace App\Jobs;

use App\Services\Marketplace\ShopeeOrderShadowRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional queue wrapper around ShopeeOrderShadowRecorder::recordFromPush (e.g. simulate --dispatch-job).
 * Normal path: lazy load on /omnichannel/shopee/{ordersn}. Read-only — no transactions / stock.
 */
class RecordShopeePushShadowJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $shopId,
        public string $orderSn,
        public ?string $pushStatus,
        public int $pushCode = 3,
    ) {}

    public function handle(ShopeeOrderShadowRecorder $recorder): void
    {
        try {
            $recorder->recordFromPush($this->shopId, $this->orderSn, $this->pushStatus, $this->pushCode);
        } catch (Throwable $e) {
            Log::warning('Shopee shadow record failed', [
                'ordersn' => $this->orderSn,
                'message' => $e->getMessage(),
            ]);
        }
    }
}