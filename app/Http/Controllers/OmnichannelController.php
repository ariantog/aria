<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Omnichannel;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Services\Marketplace\OmnichannelOrderDetailReader;
use App\Services\Marketplace\OmnichannelCompareDashboardStats;
use App\Services\Marketplace\OmnichannelPostSellDashboardStats;
use App\Services\Marketplace\OmnichannelReceivedTimes;
use App\Services\Marketplace\ShopeeOrderShadowRecorder;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OmnichannelController extends Controller
{
    public function index(
        Request $request,
        ShopeeAdminOpenApiService $shopeeAdmin,
        OmnichannelReceivedTimes $receivedTimes,
        OmnichannelPostSellDashboardStats $postSellStats,
        OmnichannelCompareDashboardStats $compareStats,
    ): View
    {
        Gate::authorize(Omnichannel::getPermissions()['view']);

        $compareFilter = $request->query('compare');
        $lineFilter = $request->query('line');
        $invoiceSearch = trim((string) $request->query('invoice', ''));

        $query = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->orderByDesc('last_event_at')
            ->orderByDesc('id');

        if (is_string($compareFilter) && $compareFilter !== '') {
            $query->where('compare_status', $compareFilter);
        }

        if (is_string($lineFilter) && $lineFilter !== '') {
            $query->where('line_compare_status', $lineFilter);
        }

        if ($invoiceSearch !== '') {
            $query->where(function ($q) use ($invoiceSearch) {
                $q->where('external_order_sn', 'like', '%'.$invoiceSearch.'%')
                    ->orWhere('jubelio_invoice', 'like', '%'.$invoiceSearch.'%');
            });
        }

        $shadows = $query->paginate(25)->withQueryString();

        $compareCounts = $compareStats->counts();
        $stats = [
            'matched' => $compareCounts['matched'],
            'mismatch' => $compareCounts['mismatch'],
            'jubelio_missing' => $compareCounts['jubelio_missing'],
            'pending' => $compareCounts['pending'],
            'line_mismatch' => $compareCounts['line_mismatch'],
        ];

        $jubelioPending = (int) $compareCounts['jubelio_pending_sell'];
        $nativePostSellStats = $postSellStats->counts();

        $shopeeAdminConnection = $shopeeAdmin->getConnectionStatus();
        $ingressByShadowId = $receivedTimes->forShadows($shadows);

        return view('omnichannel.index', [
            'shadows' => $shadows,
            'ingressByShadowId' => $ingressByShadowId,
            'stats' => $stats,
            'filters' => [
                'compare' => $compareFilter,
                'line' => $lineFilter,
                'invoice' => $invoiceSearch,
            ],
            'jubelioPending' => $jubelioPending,
            'nativePostSellStats' => $nativePostSellStats,
            'jubelioActive' => (bool) config('services.jubelio.active'),
            'shopeePushConfigured' => filled(config('services.shopee_push.partner_key')),
            'shopeeShadowEnabled' => (bool) config('services.shopee_push.shadow_enabled'),
            'shopeeAdminConnection' => $shopeeAdminConnection,
            'shopeeAdminConfigured' => $shopeeAdmin->isConfigured(),
            'shopeeAdminOAuthError' => $shopeeAdmin->formatOAuthErrorForUser($shopeeAdmin->getLastOAuthError()),
            'canJubelio' => Gate::check(\App\Models\Jubelio::getPermissions()['view']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function showShopee(
        Request $request,
        string $ordersn,
        ShopeeOrderShadowRecorder $shadowRecorder,
        OmnichannelReceivedTimes $receivedTimes,
        OmnichannelOrderDetailReader $detailReader,
        MarketplaceWebhookStatusPresenter $statusPresenter,
    ): View {
        Gate::authorize(Omnichannel::getPermissions()['view']);

        $shadow = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->where('external_order_sn', $ordersn)
            ->firstOrFail();

        $detailFetchedNow = false;
        if (! $shadow->detailFresh()) {
            $shadow = $shadowRecorder->recordFromPush(
                $shadow->shop_id,
                $ordersn,
                $shadow->channel_status,
                (int) ($shadow->last_push_code ?? 3),
            );
            $detailFetchedNow = true;
        }

        $orderDetail = $detailReader->build(MarketplaceOrderShadow::CHANNEL_SHOPEE, $ordersn);

        return view('omnichannel.shopee-show', array_merge(
            $this->shopeeShowViewData($shadow, $ordersn, $receivedTimes),
            [
                'detailFetchedNow' => $detailFetchedNow,
                'detailFromCache' => ! $detailFetchedNow && $shadow->detailLoaded(),
                'orderDetail' => $orderDetail,
                'statusPresenter' => $statusPresenter,
            ],
        ));
    }

    public function refreshShopee(string $ordersn, ShopeeOrderShadowRecorder $shadowRecorder): RedirectResponse
    {
        Gate::authorize(Omnichannel::getPermissions()['view']);

        $shadow = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->where('external_order_sn', $ordersn)
            ->firstOrFail();

        $shadowRecorder->recordFromPush(
            $shadow->shop_id,
            $ordersn,
            $shadow->channel_status,
            (int) ($shadow->last_push_code ?? 3),
        );

        return redirect()
            ->route('omnichannel.shopee.show', $ordersn)
            ->with('success', 'Shadow Shopee diperbarui dari API (read-only).');
    }

    /**
     * @return array<string, mixed>
     */
    private function shopeeShowViewData(
        MarketplaceOrderShadow $shadow,
        string $ordersn,
        OmnichannelReceivedTimes $receivedTimes,
        ?MarketplaceWebhookShopWarehouseResolver $shopResolver = null,
    ): array {
        $jubelioOrder = null;
        if ($shadow->jubelio_order_id) {
            $jubelioOrder = Jubelioorder::query()->find($shadow->jubelio_order_id);
        }

        $payloadSnippet = null;
        if (is_array($shadow->channel_payload)) {
            $orderRow = $shadow->channel_payload['response']['order_list'][0] ?? null;
            if (is_array($orderRow)) {
                $payloadSnippet = [
                    'order_sn' => $orderRow['order_sn'] ?? $ordersn,
                    'order_status' => $orderRow['order_status'] ?? null,
                    'total_amount' => $orderRow['total_amount'] ?? null,
                    'item_list' => $orderRow['item_list'] ?? [],
                ];
            }
        }

        $warehouse = [];
        $shopResolver ??= app(MarketplaceWebhookShopWarehouseResolver::class);
        if (is_array($shadow->channel_payload)) {
            $orderRow = $shadow->channel_payload['response']['order_list'][0] ?? null;
            $pushPayload = is_array($orderRow)
                ? ['item_list' => $orderRow['item_list'] ?? [], 'package_list' => $orderRow['package_list'] ?? []]
                : null;
            $row = (object) [
                'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
                'shop_ref' => $shadow->shop_id !== null && (int) $shadow->shop_id > 0
                    ? (string) $shadow->shop_id
                    : null,
                'payload' => $pushPayload,
            ];
            $warehouse = $shopResolver->warehouseDisplayForRow(
                $row,
                $shopResolver->shopRefToWarehouseMap(),
                $shopResolver->warehouseOptions(),
            );
        }

        return [
            'shadow' => $shadow,
            'jubelioOrder' => $jubelioOrder,
            'payloadSnippet' => $payloadSnippet,
            'ingress' => $receivedTimes->forSingle($shadow, $jubelioOrder),
            'warehouse' => $warehouse,
            'canJubelio' => Gate::check(\App\Models\Jubelio::getPermissions()['view']),
        ];
    }
}
