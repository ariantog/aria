<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\Transaction;
use App\Models\MarketplaceChannelOrderAwbPrint;
use App\Models\User;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWebhookEvent;
use App\Models\TiktokShopSync;
use App\Services\TikTokShop\TikTokShopOpenApiService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only order detail from stored webhook/shadow rows; optional TikTok API enrich on expand.
 */
class OmnichannelOrderDetailReader
{
    public function __construct(
        protected OmnichannelReceivedTimes $receivedTimes,
        protected MarketplaceTikTokCommercePlatformEnricher $commercePlatformEnricher,
        protected TikTokShopOpenApiService $tikTokOpenApi,
        protected TikTokOrderLineSummary $tikTokLineSummary,
        protected ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
        protected MarketplaceWebhookShopWarehouseResolver $shopWarehouseResolver,
        protected OmnichannelPostSellEligibility $postSellEligibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $channel, string $orderRef): array
    {
        $channel = strtolower($channel);
        $orderRef = trim($orderRef);

        $channelOrder = null;
        $commercePlatform = null;
        if (Schema::hasTable('marketplace_channel_orders')) {
            $channelOrder = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->first();
            $commercePlatform = filled($channelOrder?->commerce_platform)
                ? strtoupper((string) $channelOrder->commerce_platform)
                : null;
        }

        $webhookEvents = [];
        if (Schema::hasTable('marketplace_webhook_events')) {
            $webhookEvents = MarketplaceWebhookEvent::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'event_type', 'order_status', 'shop_ref', 'signature_ok', 'received_at', 'commerce_platform'])
                ->map(fn (MarketplaceWebhookEvent $row) => [
                    'id' => $row->id,
                    'event_type' => $row->event_type,
                    'order_status' => $row->order_status,
                    'shop_ref' => $row->shop_ref,
                    'signature_ok' => (bool) $row->signature_ok,
                    'received_at' => $row->received_at?->toIso8601String(),
                    'commerce_platform' => filled($row->commerce_platform)
                        ? strtoupper((string) $row->commerce_platform)
                        : null,
                ])
                ->all();
        }

        $lineSummary = [];
        $channelOrderSnippet = null;
        $apiDetailLoaded = false;
        $apiError = null;
        $apiDetail = null;

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            $tiktokApi = $this->loadTikTokOrderApiDetail($orderRef, $webhookEvents, $channelOrder);
            $lineSummary = $tiktokApi['line_summary'];
            $channelOrderSnippet = $tiktokApi['channel_order'];
            $apiDetailLoaded = $tiktokApi['api_detail_loaded'];
            $apiError = $tiktokApi['api_error'];
            $apiDetail = $tiktokApi['api_detail'];

            if ($commercePlatform === null && filled($tiktokApi['commerce_platform'] ?? null)) {
                $commercePlatform = $tiktokApi['commerce_platform'];
                $this->commercePlatformEnricher->persistCommercePlatform(
                    MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
                    $orderRef,
                    $commercePlatform,
                    $channelOrder,
                );
            }
        }

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK && $commercePlatform === null) {
            $commercePlatform = $this->commercePlatformEnricher->enrichOrder($orderRef);
        }

        $shadow = null;
        $ingress = null;
        $shadowModel = null;
        if ($channel === MarketplaceOrderShadow::CHANNEL_SHOPEE && Schema::hasTable('marketplace_order_shadows')) {
            $shadowModel = MarketplaceOrderShadow::query()
                ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
                ->where('external_order_sn', $orderRef)
                ->first();

            if ($shadowModel !== null) {
                $shadow = [
                    'id' => $shadowModel->id,
                    'compare_status' => $shadowModel->compare_status,
                    'line_compare_status' => $shadowModel->line_compare_status,
                    'channel_status' => $shadowModel->channel_status,
                    'compare_note' => $shadowModel->compare_note,
                    'line_summary' => $shadowModel->line_summary,
                    'jubelio_line_summary' => $shadowModel->jubelio_line_summary,
                    'detail_loaded' => $shadowModel->detailLoaded(),
                ];
                $ingress = $this->receivedTimes->forSingle($shadowModel);
            }
        }

        $jubelioOrder = $this->resolveJubelioOrder($channel, $orderRef, $shadowModel);

        $warehouse = $this->resolveWarehouseForDetail($channel, $channelOrder, $webhookEvents, $shadowModel);

        $payload = [
            'channel' => $channel,
            'order_ref' => $orderRef,
            'commerce_platform' => $commercePlatform,
            'warehouse' => $warehouse,
            'webhook_events' => $webhookEvents,
            'shadow' => $shadow,
            'ingress' => $ingress,
            'jubelio' => $jubelioOrder !== null ? [
                'id' => $jubelioOrder->id,
                'invoice' => $jubelioOrder->invoice,
                'order_status' => $jubelioOrder->order_status,
                'status' => $jubelioOrder->status,
                'show_url' => Gate::check(\App\Models\Jubelio::getPermissions()['view'])
                    ? route('jubelio.show', $jubelioOrder->id)
                    : null,
            ] : null,
            'links' => $this->resolveLinks($channel, $orderRef, $jubelioOrder),
            'awb_print' => $this->resolveAwbPrintForDetail($channelOrder),
            'awb_print_history' => $this->resolveAwbPrintHistoryForDetail($channel, $orderRef),
            'sell_post' => $this->resolveSellPostForDetail($channelOrder),
        ];

        if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
            $payload['line_summary'] = $lineSummary;
            $payload['channel_order'] = $channelOrderSnippet;
            $payload['api_detail_loaded'] = $apiDetailLoaded;
            $payload['api_error'] = $apiError;
            $payload['api_detail'] = $apiDetail;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $webhookEvents
     * @return array{aria_name: ?string, platform_label: ?string, unmapped: bool}
     */
    protected function resolveWarehouseForDetail(
        string $channel,
        ?MarketplaceChannelOrder $channelOrder,
        array $webhookEvents,
        ?MarketplaceOrderShadow $shadow,
    ): array {
        $shopRef = $channelOrder?->shop_ref;
        $commercePlatform = $channelOrder?->commerce_platform;
        $payload = null;

        if ($channelOrder !== null) {
            $channelOrder->loadMissing('lastWebhookEvent');
            $payload = $this->shopWarehouseResolver->payloadForListRow($channelOrder);
        }

        if ($payload === null && $shadow !== null && is_array($shadow->channel_payload)) {
            $orderRow = $shadow->channel_payload['response']['order_list'][0] ?? null;
            if (is_array($orderRow)) {
                $payload = ['item_list' => $orderRow['item_list'] ?? []];
                if (isset($orderRow['package_list'])) {
                    $payload['package_list'] = $orderRow['package_list'];
                }
            }
            if ($shopRef === null && $shadow->shop_id !== null && (int) $shadow->shop_id > 0) {
                $shopRef = (string) $shadow->shop_id;
            }
        }

        if ($shopRef === null) {
            foreach ($webhookEvents as $event) {
                if (filled($event['shop_ref'] ?? null)) {
                    $shopRef = (string) $event['shop_ref'];
                    break;
                }
            }
        }

        $row = (object) [
            'channel' => $channel,
            'shop_ref' => $shopRef,
            'commerce_platform' => $commercePlatform,
            'payload' => $payload,
            'platform_warehouse_id' => $channelOrder?->platform_warehouse_id,
        ];

        if ($channelOrder !== null) {
            return $this->shopWarehouseResolver->warehouseDisplayForRow(
                $channelOrder,
                $this->shopWarehouseResolver->shopRefToWarehouseMap(),
                $this->shopWarehouseResolver->warehouseOptions(),
            );
        }

        return $this->shopWarehouseResolver->warehouseDisplayForRow(
            $row,
            $this->shopWarehouseResolver->shopRefToWarehouseMap(),
            $this->shopWarehouseResolver->warehouseOptions(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $webhookEvents
     * @return array{
     *     line_summary: list<array{sku: string, qty: float}>,
     *     channel_order: array<string, mixed>|null,
     *     api_detail_loaded: bool,
     *     api_error: string|null,
     *     api_detail: array<string, mixed>|null,
     *     commerce_platform: string|null,
     * }
     */
    protected function loadTikTokOrderApiDetail(
        string $orderRef,
        array $webhookEvents,
        ?MarketplaceChannelOrder $channelOrder,
    ): array {
        $empty = [
            'line_summary' => [],
            'channel_order' => null,
            'api_detail_loaded' => false,
            'api_error' => null,
            'api_detail' => null,
            'commerce_platform' => null,
        ];

        $shopRef = $this->resolveShopRefForTikTok($webhookEvents, $channelOrder);
        $shopCipher = $this->resolveTikTokShopCipher($shopRef);

        if (! $this->tikTokOpenApi->isMock() && ! $this->tikTokOpenApi->liveCredentialsReady($shopCipher, $shopRef)) {
            return array_merge($empty, [
                'api_error' => 'Kredensial TikTok Shop tidak lengkap (access_token / shop_cipher).',
            ]);
        }

        $apiResponse = $this->tikTokOpenApi->getOrderDetail($orderRef, $shopCipher, $shopRef);
        if ($apiResponse === null) {
            return array_merge($empty, [
                'api_error' => 'Get Order Detail gagal atau tidak ada respons.',
            ]);
        }

        $platform = $this->tikTokOpenApi->extractCommercePlatform($apiResponse);
        $lines = $this->tikTokLineSummary->fromOrderDetailResponse($apiResponse);
        $snippet = $this->tikTokOpenApi->buildDisplaySnippet($apiResponse);
        if ($snippet === [] && $channelOrder !== null) {
            $snippet = array_filter([
                'status' => $channelOrder->order_status,
                'commerce_platform' => filled($channelOrder->commerce_platform)
                    ? strtoupper((string) $channelOrder->commerce_platform)
                    : null,
            ], fn ($v) => filled($v));
        }

        return [
            'line_summary' => $lines,
            'channel_order' => $snippet !== [] ? $snippet : null,
            'api_detail_loaded' => true,
            'api_error' => null,
            'api_detail' => $this->tikTokOpenApi->redactDetailForDisplay($apiResponse),
            'commerce_platform' => filled($platform) ? strtoupper((string) $platform) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $webhookEvents
     */
    protected function resolveShopRefForTikTok(array $webhookEvents, ?MarketplaceChannelOrder $channelOrder): ?string
    {
        foreach ($webhookEvents as $event) {
            if (filled($event['shop_ref'] ?? null)) {
                return (string) $event['shop_ref'];
            }
        }

        if (filled($channelOrder?->shop_ref)) {
            return (string) $channelOrder->shop_ref;
        }

        return null;
    }

    protected function resolveTikTokShopCipher(?string $shopRef): ?string
    {
        if (filled($shopRef) && Schema::hasTable('tiktok_shop_syncs')) {
            $cipher = TiktokShopSync::query()
                ->where('shop_ref', $shopRef)
                ->value('shop_cipher');
            if (filled($cipher)) {
                return trim((string) $cipher);
            }
        }

        $fallback = trim((string) config('tiktok_shop.shop_cipher', ''));

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveLinks(string $channel, string $orderRef, ?Jubelioorder $jubelioOrder): array
    {
        $links = [
            'shadow_compare' => null,
            'jubelio' => null,
        ];

        if ($channel === MarketplaceOrderShadow::CHANNEL_SHOPEE) {
            $links['shadow_compare'] = route('omnichannel.shopee.show', $orderRef);
        }

        if ($jubelioOrder !== null && Gate::check(\App\Models\Jubelio::getPermissions()['view'])) {
            $links['jubelio'] = route('jubelio.show', $jubelioOrder->id);
        }

        return $links;
    }

    private function resolveJubelioOrder(
        string $channel,
        string $orderRef,
        ?MarketplaceOrderShadow $shadow,
    ): ?Jubelioorder {
        if ($shadow?->jubelio_order_id) {
            $hit = Jubelioorder::query()->find($shadow->jubelio_order_id);
            if ($hit !== null) {
                return $hit;
            }
        }

        if (filled($shadow?->jubelio_invoice)) {
            $hit = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $shadow->jubelio_invoice)
                ->first();
            if ($hit !== null) {
                return $hit;
            }
        }

        $query = Jubelioorder::query()->where('type', 'SELL');
        $query->where(function ($q) use ($channel, $orderRef, $shadow): void {
            if ($channel === 'shopee') {
                $shopId = $shadow?->shop_id !== null ? (int) $shadow->shop_id : null;
                $prefix = $this->shopeeInvoicePrefix->prefixForShopId($shopId > 0 ? $shopId : null);
                $base = $prefix.'-'.$orderRef;
                $q->where('invoice', $base)
                    ->orWhere('invoice', 'like', $prefix.'-'.$orderRef.'-%');
                if ($shopId === null || $shopId <= 0) {
                    foreach ($this->shopeeInvoicePrefix->allKnownPrefixes() as $altPrefix) {
                        if ($altPrefix === $prefix) {
                            continue;
                        }
                        $altBase = $altPrefix.'-'.$orderRef;
                        $q->orWhere('invoice', $altBase)
                            ->orWhere('invoice', 'like', $altPrefix.'-'.$orderRef.'-%');
                    }
                }
            } elseif ($channel === 'tiktok') {
                $q->where('invoice', $orderRef)
                    ->orWhere('invoice', 'like', 'TT-'.$orderRef.'%')
                    ->orWhere('invoice', 'like', '%'.$orderRef.'%');
            } else {
                $q->where('invoice', $orderRef);
            }
        });

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return array{user_id: int, username: ?string, printed_at: ?string}|null
     */
    protected function resolveAwbPrintForDetail(?MarketplaceChannelOrder $channelOrder): ?array
    {
        if ($channelOrder === null
            || ! Schema::hasTable('marketplace_channel_orders')
            || ! Schema::hasColumn('marketplace_channel_orders', 'awb_printed_by')) {
            return null;
        }

        $userId = (int) ($channelOrder->awb_printed_by ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $username = null;
        if ($channelOrder->relationLoaded('awbPrintedBy')) {
            $username = $channelOrder->awbPrintedBy?->username;
        } else {
            $username = User::query()->whereKey($userId)->value('username');
        }

        return [
            'user_id' => $userId,
            'username' => $username !== null ? (string) $username : null,
            'printed_at' => $channelOrder->awb_printed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveSellPostForDetail(?MarketplaceChannelOrder $channelOrder): ?array
    {
        if ($channelOrder === null) {
            return null;
        }

        $invoice = $this->postSellEligibility->resolveInvoice($channelOrder);
        $postedTransactionId = (int) ($channelOrder->posted_transaction_id ?? 0);

        $transaction = null;
        if ($postedTransactionId > 0) {
            $transaction = Transaction::query()->find($postedTransactionId);
        }
        if ($transaction === null && $invoice !== '') {
            $transaction = Transaction::query()
                ->where('type', Transaction::TYPE_SELL)
                ->where('invoice', $invoice)
                ->orderByDesc('id')
                ->first();
        }

        $submitType = $transaction !== null ? (int) $transaction->submit_type : null;

        return [
            'invoice' => $invoice,
            'posted_transaction_id' => $postedTransactionId > 0 ? $postedTransactionId : ($transaction?->id),
            'posted_at' => $channelOrder->posted_at?->toIso8601String(),
            'submit_type' => $submitType,
            'submit_type_label' => $submitType !== null ? Transaction::submitTypeLabel($submitType) : null,
            'transaction_url' => $transaction !== null
                ? route('transactions.show', $transaction->id)
                : null,
        ];
    }

    /**
     * @return list<array{user_id: int, username: ?string, printed_at: string, is_reprint: bool}>
     */
    protected function resolveAwbPrintHistoryForDetail(string $channel, string $orderRef): array
    {
        if (! Schema::hasTable('marketplace_channel_order_awb_prints')) {
            return [];
        }

        $rows = MarketplaceChannelOrderAwbPrint::query()
            ->with('user:id,username')
            ->where('channel', $channel)
            ->where('order_ref', $orderRef)
            ->orderByDesc('printed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'user_id' => (int) $row->user_id,
                'username' => $row->user?->username,
                'printed_at' => $row->printed_at?->toIso8601String() ?? '',
                'is_reprint' => (bool) $row->is_reprint,
            ];
        }

        return $history;
    }
}