<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Omnichannel;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OmnichannelController extends Controller
{
    public function index(Request $request, ShopeeStockOpenApiService $shopeeStock): View
    {
        $user = $request->user();
        abort_unless(Omnichannel::userCanView($user), 403);

        $compareFilter = $request->query('compare');
        $invoiceSearch = trim((string) $request->query('invoice', ''));

        $query = MarketplaceOrderShadow::query()
            ->where('channel', MarketplaceOrderShadow::CHANNEL_SHOPEE)
            ->orderByDesc('last_event_at')
            ->orderByDesc('id');

        if (is_string($compareFilter) && $compareFilter !== '') {
            $query->where('compare_status', $compareFilter);
        }

        if ($invoiceSearch !== '') {
            $query->where(function ($q) use ($invoiceSearch) {
                $q->where('external_order_sn', 'like', '%'.$invoiceSearch.'%')
                    ->orWhere('jubelio_invoice', 'like', '%'.$invoiceSearch.'%');
            });
        }

        $shadows = $query->paginate(25)->withQueryString();

        $stats = [
            'matched' => MarketplaceOrderShadow::query()->where('compare_status', MarketplaceOrderShadow::COMPARE_MATCHED)->count(),
            'mismatch' => MarketplaceOrderShadow::query()->where('compare_status', MarketplaceOrderShadow::COMPARE_MISMATCH)->count(),
            'jubelio_missing' => MarketplaceOrderShadow::query()->where('compare_status', MarketplaceOrderShadow::COMPARE_JUBELIO_MISSING)->count(),
            'pending' => MarketplaceOrderShadow::query()->where('compare_status', MarketplaceOrderShadow::COMPARE_PENDING)->count(),
        ];

        $jubelioPending = Jubelioorder::query()->where('type', 'SELL')->where('status', 0)->count();

        $shopeeConnection = $shopeeStock->getConnectionStatus();

        return view('omnichannel.index', [
            'shadows' => $shadows,
            'stats' => $stats,
            'filters' => [
                'compare' => $compareFilter,
                'invoice' => $invoiceSearch,
            ],
            'jubelioPending' => $jubelioPending,
            'jubelioActive' => (bool) config('services.jubelio.active'),
            'shopeePushConfigured' => filled(config('services.shopee_push.partner_key')),
            'shopeeShadowEnabled' => (bool) config('services.shopee_push.shadow_enabled'),
            'shopeeConnection' => $shopeeConnection,
            'canJubelio' => Gate::check(\App\Models\Jubelio::getPermissions()['view']),
        ]);
    }
}