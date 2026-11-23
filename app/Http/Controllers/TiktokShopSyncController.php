<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceWarehouseSync;
use App\Models\TiktokShopSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy TikTok routes → unified marketplace warehouse sync (channel=tiktok).
 */
class TiktokShopSyncController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('marketplace.warehouse-sync.index', array_merge(
            $request->query(),
            ['channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK],
        ));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('marketplace.warehouse-sync.create', [
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return app()->call([MarketplaceWarehouseSyncController::class, 'store'], [
            'request' => $request,
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        ]);
    }

    public function edit(TiktokShopSync $sync): RedirectResponse
    {
        return redirect()->route('marketplace.warehouse-sync.matrix', [
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'shopRef' => $sync->shop_ref,
        ]);
    }

    public function update(Request $request, TiktokShopSync $sync): RedirectResponse
    {
        return $this->edit($sync);
    }

    public function destroy(TiktokShopSync $sync): RedirectResponse
    {
        $shopRef = $sync->shop_ref;
        $sync->delete();

        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            MarketplaceWarehouseSync::query()
                ->where('channel', MarketplaceWarehouseSync::CHANNEL_TIKTOK)
                ->where('shop_ref', $shopRef)
                ->delete();
        }

        return redirect()
            ->route('marketplace.warehouse-sync.index', ['channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK])
            ->with('success', 'Mapping TikTok Shop dihapus.');
    }

    public function matrix(Request $request, string $shopRef): RedirectResponse
    {
        return redirect()->route('marketplace.warehouse-sync.matrix', [
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'shopRef' => $shopRef,
        ]);
    }

    public function updateMatrix(Request $request, string $shopRef): RedirectResponse
    {
        return app()->call([MarketplaceWarehouseSyncController::class, 'updateMatrix'], [
            'request' => $request,
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'shopRef' => $shopRef,
        ]);
    }

    public function refreshMatrix(string $shopRef): RedirectResponse
    {
        return app()->call([MarketplaceWarehouseSyncController::class, 'refreshMatrix'], [
            'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
            'shopRef' => $shopRef,
        ]);
    }
}