<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\MarketplaceWebhookRecorder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shared ingest for Shopee order-status updates (push webhook or Open API poll).
 */
class ShopeeOrderStatusIngest
{
    public function __construct(
        private ShopeeOrderShadowRecorder $shadowRecorder,
        private ShopeeJubelioQueuePrecreator $jubelioQueuePrecreator,
        private MarketplaceWebhookRecorder $webhookRecorder,
        private MarketplaceWebhookStatusPresenter $statusPresenter,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
    ) {}

    /**
     * @param  array<string, mixed>  $pushData  Shopee `data` object (status, item_list, …)
     * @param  array<string, mixed>  $fullPayload  Whole envelope (code, shop_id, timestamp)
     * @return array{shadow: bool, jubelio_placeholder: bool, webhook_event: bool}
     */
    public function ingest(
        ?int $shopId,
        string $orderSn,
        ?string $orderStatus,
        int $pushCode,
        array $pushData = [],
        array $fullPayload = [],
        bool $recordWebhookWhenUnified = false,
    ): array {
        $orderSn = trim($orderSn);
        $result = [
            'shadow' => false,
            'jubelio_placeholder' => false,
            'webhook_event' => false,
        ];

        if ($orderSn === '' || $pushCode !== 3) {
            return $result;
        }

        if (config('services.shopee_push.shadow_enabled')) {
            try {
                if (Schema::hasTable('marketplace_order_shadows')) {
                    $this->shadowRecorder->upsertStubFromPush(
                        $shopId,
                        $orderSn,
                        $orderStatus,
                        $pushCode,
                        $pushData,
                        $fullPayload,
                    );
                    $result['shadow'] = true;
                }
            } catch (Throwable $e) {
                Log::warning('Shopee order ingest shadow stub failed', [
                    'ordersn' => $orderSn,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! $this->invoicePrefix->isFlexiExportShop($shopId)) {
            try {
                $result['jubelio_placeholder'] = $this->jubelioQueuePrecreator->upsertFromPush(
                    $shopId,
                    $orderSn,
                    $orderStatus,
                    $pushCode,
                    $pushData,
                    $fullPayload,
                );
            } catch (Throwable $e) {
                Log::warning('Shopee order ingest jubelio placeholder failed', [
                    'ordersn' => $orderSn,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($recordWebhookWhenUnified) {
            try {
                $this->webhookRecorder->recordShopee(
                    $fullPayload,
                    true,
                    $shopId,
                    $pushCode,
                    $orderSn,
                    $orderStatus ?? $this->statusPresenter->resolveIngestStatus(
                        MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
                        $pushCode,
                        $fullPayload,
                    ),
                );
                $result['webhook_event'] = true;
            } catch (Throwable $e) {
                Log::warning('Shopee order ingest unified webhook failed', [
                    'ordersn' => $orderSn,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }
}