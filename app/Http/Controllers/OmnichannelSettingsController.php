<?php

namespace App\Http\Controllers;

use App\Models\Jubelio;
use App\Models\Jubeliosync;
use App\Models\Omnichannel;
use App\Models\ShopeeStock;
use App\Models\Shopeesync;
use App\Models\TikTokShop;
use App\Models\TiktokShopSync;
use App\Services\JubelioService;
use App\Services\Marketplace\OmnichannelSettingStore;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\Shopee\ShopeeStockOpenApiService;
use App\Services\TikTokShop\TikTokShopOAuthService;
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
        TikTokShopOAuthService $tikTokOAuth,
        JubelioService $jubelioService,
    ): View {
        abort_unless(Omnichannel::userCanSettings($request->user()), 403);

        $tab = strtolower((string) $request->query('tab', 'connections'));
        if (! in_array($tab, ['warehouse', 'low-stock', 'connections'], true)) {
            $tab = 'connections';
        }

        $jubelioSyncRows = collect();
        $tokopediaSyncRows = collect();
        $shopeeSyncRows = collect();
        $tiktokSyncRows = collect();
        if ($tab === 'warehouse') {
            if (Gate::check(Jubelio::getPermissions()['sync'])) {
                $jubelioSyncRows = Jubeliosync::query()
                    ->with(['warehouse', 'customer'])
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get();
                $tokopediaSyncRows = Jubeliosync::query()
                    ->with(['warehouse', 'customer'])
                    ->where('jubelio_store_name', 'LIKE', '%tokopedia%')
                    ->orderByDesc('created_at')
                    ->get();
            }
            if (Gate::check(ShopeeStock::getPermissions()['sync'])) {
                $shopeeSyncRows = Shopeesync::query()
                    ->with(['warehouse'])
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get();
            }
            if (Gate::check(TikTokShop::getPermissions()['sync'])
                || Omnichannel::userCanSettingsEdit($request->user())) {
                $tiktokSyncRows = TiktokShopSync::query()
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
        $tokopediaStoreId = $settings->getString(OmnichannelSettingStore::KEY_TOKOPEDIA_STORE_ID, '');
        $tokopediaNotes = $settings->getString(OmnichannelSettingStore::KEY_TOKOPEDIA_NOTES, '');

        return view('omnichannel.settings', [
            'tab' => $tab,
            'canEditLowStock' => Omnichannel::userCanSettingsEdit($request->user()),
            'canJubelioSync' => Gate::check(Jubelio::getPermissions()['sync']),
            'canShopeeSync' => Gate::check(ShopeeStock::getPermissions()['sync']),
            'canTiktokSync' => Gate::check(TikTokShop::getPermissions()['sync'])
                || Omnichannel::userCanSettingsEdit($request->user()),
            'jubelioSyncRows' => $jubelioSyncRows,
            'tokopediaSyncRows' => $tokopediaSyncRows,
            'shopeeSyncRows' => $shopeeSyncRows,
            'tiktokSyncRows' => $tiktokSyncRows,
            'lowStockThreshold' => $lowStockThreshold,
            'tokopediaStoreId' => $tokopediaStoreId ?? '',
            'tokopediaNotes' => $tokopediaNotes ?? '',
            'settingsTableExists' => $settings->tableExists(),
            'shopeeAdminConnection' => $shopeeAdmin->getConnectionStatus(),
            'shopeeStockConnection' => $shopeeStockOpen->getConnectionStatus(),
            'jubelioConnection' => $jubelioService->getConnectionStatus(),
            'shopeePushConfigured' => filled(config('services.shopee_push.partner_key')),
            'tiktokAppConfigured' => filled(config('tiktok_shop.app_key')) && filled(config('tiktok_shop.app_secret')),
            'tiktokOAuthConnection' => $tikTokOAuth->getConnectionStatus(),
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

    public function updateTokopedia(Request $request, OmnichannelSettingStore $settings): RedirectResponse
    {
        abort_unless(Omnichannel::userCanSettingsEdit($request->user()), 403);
        abort_unless($settings->tableExists(), 503, 'Migrasi omnichannel_settings belum dijalankan.');

        $validated = $request->validate([
            'tokopedia_store_id' => 'nullable|string|max:64',
            'tokopedia_notes' => 'nullable|string|max:2000',
        ]);

        $settings->set(
            OmnichannelSettingStore::KEY_TOKOPEDIA_STORE_ID,
            trim((string) ($validated['tokopedia_store_id'] ?? '')),
        );
        $settings->set(
            OmnichannelSettingStore::KEY_TOKOPEDIA_NOTES,
            trim((string) ($validated['tokopedia_notes'] ?? '')),
        );

        return redirect()
            ->route('omnichannel.settings.index', ['tab' => 'warehouse'])
            ->with('success', 'Catatan Tokopedia disimpan.');
    }
}