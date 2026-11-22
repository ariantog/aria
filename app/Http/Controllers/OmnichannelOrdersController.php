<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceChannelOrder;
use App\Models\Omnichannel;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use App\Services\Marketplace\OmnichannelOrderDetailReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OmnichannelOrdersController extends Controller
{
    public function index(
        Request $request,
        MarketplaceWebhookInboxQuery $inboxQuery,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $filters = $inboxQuery->parseFilters($request);
        $filters['view'] = 'orders';

        $ordersTableExists = Schema::hasTable('marketplace_channel_orders');
        $rows = null;
        $statusOptions = [];

        if ($ordersTableExists) {
            $rows = $inboxQuery->ordersQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
            $statusOptions = $inboxQuery->distinctOrderStatuses($filters, 'orders');
        }

        $warehouseOptions = $shopResolver->warehouseOptions();
        $shopOptions = $shopResolver->distinctShopRefsForChannel(
            $filters['channel'] === 'all' ? null : $filters['channel'],
        );
        $shopRefToWarehouse = $shopResolver->shopRefToWarehouseMap();

        return view('omnichannel.orders', [
            'filters' => $filters,
            'rows' => $rows,
            'ordersTableExists' => $ordersTableExists,
            'statusOptions' => $statusOptions,
            'warehouseOptions' => $warehouseOptions,
            'shopOptions' => $shopOptions,
            'shopRefToWarehouse' => $shopRefToWarehouse,
            'warehouseNames' => $warehouseOptions,
            'detailUrlTemplate' => url('/omnichannel/orders/__CHANNEL__/__ORDER__'),
        ]);
    }

    public function show(
        Request $request,
        string $channel,
        string $orderRef,
        OmnichannelOrderDetailReader $detailReader,
    ): JsonResponse|View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $channel = strtolower($channel);
        if (! in_array($channel, ['shopee', 'tiktok'], true)) {
            abort(404);
        }

        if (Schema::hasTable('marketplace_channel_orders')) {
            $exists = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->exists();
            if (! $exists) {
                abort(404);
            }
        }

        $payload = $detailReader->build($channel, $orderRef);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($payload);
        }

        return view('omnichannel.partials.order-detail-panel', [
            'detail' => $payload,
        ]);
    }
}