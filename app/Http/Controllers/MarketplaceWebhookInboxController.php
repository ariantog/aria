<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceWebhook;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MarketplaceWebhookInboxController extends Controller
{
    public function index(
        Request $request,
        MarketplaceWebhookInboxQuery $inboxQuery,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
    ): View {
        abort_unless(MarketplaceWebhook::userCanView($request->user()), 403);

        $filters = $inboxQuery->parseFilters($request);
        $eventsTableExists = Schema::hasTable('marketplace_webhook_events');
        $ordersTableExists = Schema::hasTable('marketplace_channel_orders');

        $rows = null;
        $statusOptions = [];

        if ($filters['view'] === 'orders' && $ordersTableExists) {
            $rows = $inboxQuery->ordersQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
            $statusOptions = $inboxQuery->distinctOrderStatuses($filters, 'orders');
        } elseif ($eventsTableExists) {
            $rows = $inboxQuery->eventsQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
            $statusOptions = $inboxQuery->distinctOrderStatuses($filters, 'events');
        }

        $warehouseOptions = $shopResolver->warehouseOptions();
        $shopOptions = $shopResolver->distinctShopRefsForChannel(
            $filters['channel'] === 'all' ? null : $filters['channel'],
        );

        $shopRefToWarehouse = $shopResolver->shopRefToWarehouseMap();
        $warehouseNames = $warehouseOptions;

        return view('marketplace.webhooks', [
            'filters' => $filters,
            'rows' => $rows,
            'eventsTableExists' => $eventsTableExists,
            'ordersTableExists' => $ordersTableExists,
            'statusOptions' => $statusOptions,
            'warehouseOptions' => $warehouseOptions,
            'shopOptions' => $shopOptions,
            'shopRefToWarehouse' => $shopRefToWarehouse,
            'warehouseNames' => $warehouseNames,
        ]);
    }
}