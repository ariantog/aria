<?php

namespace App\Http\Controllers;

use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeAdminOAuthAuthorize;
use App\Support\ShopeeAdminOAuthCallbackHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopeeAdminController extends Controller
{
    public function authorizeShop(Request $request, ShopeeAdminOpenApiService $api): RedirectResponse
    {
        ShopeeAdminOAuthAuthorize::abortUnlessMayAuthorize($request);

        if (! $api->isConfigured()) {
            $return = (string) $request->query('return', 'omnichannel');
            $route = ShopeeAdminOAuthAuthorize::RETURN_QUERY_TO_ROUTE[$return] ?? ShopeeAdminOAuthAuthorize::RETURN_QUERY_TO_ROUTE['omnichannel'];

            return redirect()
                ->route($route)
                ->with('error', 'SHOPEE_ADMIN_PARTNER_ID / SHOPEE_ADMIN_PARTNER_KEY belum dikonfigurasi di .env (app CORENATION ADMIN).');
        }

        ShopeeAdminOAuthAuthorize::rememberReturnRoute($request);

        return redirect()->away($api->buildAuthorizeUrl());
    }

    public function oauthCallback(
        Request $request,
        ShopeeAdminOpenApiService $api,
        ShopeeAdminOAuthCallbackHandler $handler,
    ): RedirectResponse {
        return $handler->handle($request, $api, ShopeeAdminOAuthAuthorize::pullReturnRoute());
    }
}