<?php

namespace App\Jobs;

use App\Services\Marketplace\ShopeeOrderShadowRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only observability: Shopee API GET + upsert marketplace_order_shadows only.
 * Does not call ProcessJubelioOrder or write transactions / stock.
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