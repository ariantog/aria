<?php

namespace App\Http\Controllers;

use App\Models\ShopeeStock;
use App\Services\Marketplace\ShopeeFlexiOrderLookupService;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeFlexiOrderLookupController extends Controller
{
    public function show(Request $request, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $status = $shopeeApi->getConnectionStatus();

        return view('shopee.flexi.lookup', [
            'authorizedShopIds' => $status['authorized_shop_ids'] ?? [],
            'orderRef' => (string) $request->old('order_ref', ''),
            'shopId' => (string) $request->old('shop_id', ''),
            'result' => null,
        ]);
    }

    public function store(Request $request, ShopeeFlexiOrderLookupService $lookup, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $status = $shopeeApi->getConnectionStatus();

        return view('shopee.flexi.lookup', [
            'authorizedShopIds' => $status['authorized_shop_ids'] ?? [],
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'result' => $lookup->lookup($shopId, $orderRef),
        ]);
    }
}