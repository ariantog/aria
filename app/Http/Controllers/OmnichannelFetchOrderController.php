<?php

namespace App\Http\Controllers;

use App\Models\Omnichannel;
use App\Services\Marketplace\MarketplaceOrderRefPullService;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OmnichannelFetchOrderController extends Controller
{
    public function index(
        Request $request,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $selectedChannel = strtolower((string) $request->query('channel', 'shopee'));
        if (! in_array($selectedChannel, ['shopee', 'tiktok', 'tokopedia'], true)) {
            $selectedChannel = 'shopee';
        }

        $channelForShops = $selectedChannel === 'tokopedia' ? 'tokopedia' : $selectedChannel;
        $shopSelectOptions = $shopResolver->mappedShopSelectOptionsForChannel($channelForShops);
        if ($shopSelectOptions === []) {
            $fallbackRefs = $shopResolver->distinctMappedShopRefsForChannel(
                $selectedChannel === 'tokopedia' ? 'tiktok' : $channelForShops,
            );
            if ($fallbackRefs->isEmpty()) {
                $fallbackRefs = $shopResolver->distinctShopRefsForChannel(
                    $selectedChannel === 'tokopedia' ? 'tiktok' : $channelForShops,
                );
            }
            foreach ($fallbackRefs as $ref) {
                $shopSelectOptions[] = ['value' => (string) $ref, 'label' => (string) $ref];
            }
        }

        return view('omnichannel.fetch-order', [
            'selectedChannel' => $selectedChannel,
            'shopSelectOptions' => $shopSelectOptions,
            'unifiedStorage' => (bool) config('marketplace.webhook.unified_storage', false),
            'fetchResult' => session('fetch_result'),
        ]);
    }

    public function store(Request $request, MarketplaceOrderRefPullService $pullService): RedirectResponse
    {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:shopee,tiktok,tokopedia'],
            'order_ref' => ['required', 'string', 'max:128'],
            'shop_ref' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $pullService->pull(
            (string) $validated['channel'],
            (string) $validated['order_ref'],
            filled($validated['shop_ref'] ?? null) ? (string) $validated['shop_ref'] : null,
        );

        if ($result['error'] !== null) {
            return redirect()
                ->route('omnichannel.fetch-order.index', ['channel' => $validated['channel']])
                ->withInput()
                ->with('error', $result['error']);
        }

        $fetchPayload = [
            'ok' => true,
            'channel' => $result['channel'],
            'order_ref' => $result['order_ref'],
            'order_status' => $result['order_status'] ?? null,
            'shop_ref' => $result['shop_ref'] ?? null,
            'commerce_platform' => $result['commerce_platform'] ?? null,
            'return_signal' => (bool) ($result['return_signal'] ?? false),
            'webhook_event' => (bool) ($result['webhook_event'] ?? false),
            'warning' => filled($result['warning'] ?? null) ? trim((string) $result['warning']) : null,
        ];

        return redirect()
            ->route('omnichannel.fetch-order.index', ['channel' => $validated['channel']])
            ->with('fetch_result', $fetchPayload);
    }
}