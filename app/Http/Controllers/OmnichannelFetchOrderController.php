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

        $channelForShops = $selectedChannel === 'tokopedia' ? 'tiktok' : $selectedChannel;
        $shopOptions = $shopResolver->distinctMappedShopRefsForChannel($channelForShops);
        if ($shopOptions->isEmpty()) {
            $shopOptions = $shopResolver->distinctShopRefsForChannel($channelForShops);
        }

        return view('omnichannel.fetch-order', [
            'selectedChannel' => $selectedChannel,
            'shopOptions' => $shopOptions,
            'unifiedStorage' => (bool) config('marketplace.webhook.unified_storage', false),
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

        $status = $result['order_status'] ?? '—';
        $platform = filled($result['commerce_platform'] ?? null)
            ? ' ('.$result['commerce_platform'].')'
            : '';
        $message = "Order {$result['order_ref']} ditarik — status: {$status}{$platform}.";

        $redirect = redirect()->route('omnichannel.orders.show', [
            'channel' => $result['channel'],
            'orderRef' => $result['order_ref'],
        ])->with('success', $message);

        if ($result['return_signal'] ?? false) {
            $redirect->with('warning', 'Order memenuhi sinyal return marketplace — cek Marketplace returns (verifikasi fisik sebelum post).');
        }

        if (filled($result['warning'] ?? null)) {
            $redirect->with('warning', trim((string) $result['warning']));
        }

        return $redirect;
    }
}