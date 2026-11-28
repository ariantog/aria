<?php

namespace App\Jobs;

use App\Models\ShopeePushInbox;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Services\Marketplace\ShopeeOrderStatusIngest;
use App\Services\Marketplace\OmnichannelReturnShopeeListingCorrector;
use App\Services\Marketplace\ShopeePushWebhookOnOrderApplicator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PersistShopeePushInboxJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public bool $signatureOk = true,
        public ?string $callbackUrl = null,
    ) {}

    public function handle(
        ShopeeOrderStatusIngest $orderIngest,
        MarketplaceWebhookRecorder $webhookRecorder,
        MarketplaceWebhookStatusPresenter $statusPresenter,
        ShopeePushWebhookOnOrderApplicator $onOrderApplicator,
        OmnichannelReturnShopeeListingCorrector $returnListingCorrector,
    ): void {
        $data = is_array($this->payload['data'] ?? null) ? $this->payload['data'] : [];
        $shopId = isset($this->payload['shop_id']) ? (int) $this->payload['shop_id'] : null;
        $pushCode = isset($this->payload['code']) ? (int) $this->payload['code'] : null;
        $ordersn = $this->resolveOrderSn($data);
        $orderStatus = $statusPresenter->resolveIngestStatus(
            MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
            $pushCode,
            $this->payload,
        );

        if ($pushCode === 3 && filled($ordersn)) {
            try {
                $orderIngest->ingest(
                    $shopId,
                    $ordersn,
                    $orderStatus,
                    $pushCode,
                    $data,
                    $this->payload,
                );
            } catch (Throwable $e) {
                Log::warning('Shopee push order ingest failed (queued)', [
                    'ordersn' => $ordersn,
                    'message' => $e->getMessage(),
                ]);
            }

            try {
                $onOrderApplicator->applyFromPush($this->payload);
            } catch (Throwable $e) {
                Log::warning('Shopee push on_order applicator failed (queued)', [
                    'ordersn' => $ordersn,
                    'message' => $e->getMessage(),
                ]);
            }

            try {
                $returnListingCorrector->applyFromPush($this->payload);
            } catch (Throwable $e) {
                Log::warning('Shopee return listing correction failed (queued)', [
                    'ordersn' => $ordersn,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $persistInbox = $this->shouldPersistInbox($pushCode);
        $recordUnified = $this->shouldRecordUnifiedWebhook($pushCode);

        if ($persistInbox || $recordUnified) {
            try {
                if ($persistInbox && Schema::hasTable('shopee_push_inbox')) {
                    ShopeePushInbox::query()->create([
                        'shop_id' => $shopId,
                        'push_code' => $pushCode,
                        'ordersn' => $ordersn,
                        'order_status' => $orderStatus,
                        'signature_ok' => $this->signatureOk,
                        'callback_url' => $this->callbackUrl,
                        'payload' => $this->payload,
                    ]);
                }

                if ($recordUnified) {
                    $webhookRecorder->recordShopee(
                        $this->payload,
                        $this->signatureOk,
                        $shopId,
                        $pushCode,
                        $ordersn,
                        $orderStatus,
                    );
                }
            } catch (Throwable $e) {
                Log::error('Shopee push persistence failed (queued)', [
                    'message' => $e->getMessage(),
                    'push_code' => $pushCode,
                    'ordersn' => $ordersn,
                    'inbox' => $persistInbox,
                    'unified' => $recordUnified,
                ]);
            }
        }
    }

    private function shouldPersistInbox(?int $pushCode): bool
    {
        return (bool) config('services.shopee_push.store_inbox')
            && $pushCode !== null;
    }

    private function shouldRecordUnifiedWebhook(?int $pushCode): bool
    {
        if ($pushCode === null) {
            return false;
        }

        return (bool) config('marketplace.webhook.unified_storage', false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOrderSn(array $data): ?string
    {
        if (isset($data['ordersn']) && $data['ordersn'] !== '') {
            return (string) $data['ordersn'];
        }

        if (isset($data['order_sn']) && $data['order_sn'] !== '') {
            return (string) $data['order_sn'];
        }

        return null;
    }
}