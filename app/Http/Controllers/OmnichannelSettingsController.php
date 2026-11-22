<?php

namespace App\Http\Controllers;

use App\Models\Jubelio;
use App\Models\Jubeliosync;
use App\Models\Omnichannel;
use App\Models\Shopeesync;
use App\Models\ShopeeStock;
use App\Services\JubelioService;
use App\Services\Marketplace\OmnichannelSettingStore;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OmnichannelSettingsController extends Controller
{
    public function index(
        Request $request,
        OmnichannelSettingStore $settings,
        ShopeeAdminOpenApiService $shopeeAdmin,
        ShopeeStockOpenApiService $shopeeStockOpen,
        JubelioService $jubelioService,
    ): View {
        abort_unless(Omnichannel::userCanSettings($request->user()), 403);

        $tab = strtolower((string) $request->query('tab', 'connections'));
        if (! in_array($tab, ['warehouse', 'low-stock', 'connections'], true)) {
            $tab = 'connections';
        }

        $jubelioSyncRows = collect();
        $shopeeSyncRows = collect();
        if ($tab === 'warehouse') {
            if (Gate::check(Jubelio::getPermissions()['sync'])) {
                $jubelioSyncRows = Jubeliosync::query()
                    ->with(['warehouse', 'customer'])
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get();
            }
            if (Gate::check(ShopeeStock::getPermissions()['sync'])) {
                $shopeeSyncRows = Shopeesync::query()
                    ->with(['warehouse'])
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get();
            }
        }

        $lowStockThreshold = $settings->getInt(
            OmnichannelSettingStore::KEY_SHOPEE_LOW_STOCK_THRESHOLD,
            0,
        );

        return view('omnichannel.settings', [
            'tab' => $tab,
            'canEditLowStock' => Omnichannel::userCanSettingsEdit($request->user()),
            'canJubelioSync' => Gate::check(Jubelio::getPermissions()['sync']),
            'canShopeeSync' => Gate::check(ShopeeStock::getPermissions()['sync']),
            'jubelioSyncRows' => $jubelioSyncRows,
            'shopeeSyncRows' => $shopeeSyncRows,
            'lowStockThreshold' => $lowStockThreshold,
            'settingsTableExists' => $settings->tableExists(),
            'shopeeAdminConnection' => $shopeeAdmin->getConnectionStatus(),
            'shopeeStockConnection' => $shopeeStockOpen->getConnectionStatus(),
            'jubelioConnection' => $jubelioService->getConnectionStatus(),
            'shopeePushConfigured' => filled(config('services.shopee_push.partner_key')),
            'tiktokAppConfigured' => filled(config('tiktok_shop.app_key')) && filled(config('tiktok_shop.app_secret')),
            'tiktokWebhookInbox' => (bool) config('tiktok_shop.webhook.store_inbox'),
            'jubelioActive' => (bool) config('services.jubelio.active'),
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? session('errorMessage'),
            ],
        ]);
    }

    public function updateLowStock(Request $request, OmnichannelSettingStore $settings): RedirectResponse
    {
        abort_unless(Omnichannel::userCanSettingsEdit($request->user()), 403);
        abort_unless($settings->tableExists(), 503, 'Migrasi omnichannel_settings belum dijalankan.');

        $validated = $request->validate([
            'shopee_low_stock_threshold' => 'required|integer|min:0|max:999999',
        ]);

        $settings->set(
            OmnichannelSettingStore::KEY_SHOPEE_LOW_STOCK_THRESHOLD,
            (string) $validated['shopee_low_stock_threshold'],
        );

        return redirect()
            ->route('omnichannel.settings.index', ['tab' => 'low-stock'])
            ->with('success', 'Threshold stok rendah Shopee disimpan.');
    }
}