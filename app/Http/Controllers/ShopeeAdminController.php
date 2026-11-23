<?php

namespace App\Http\Controllers;

use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeAdminOAuthAuthorize;
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

    public function oauthCallback(Request $request, ShopeeAdminOpenApiService $api): RedirectResponse
    {
        $code = $request->query('code');
        $shopId = (int) $request->query('shop_id');
        $returnRoute = session(ShopeeAdminOAuthAuthorize::SESSION_RETURN_ROUTE, 'omnichannel.index');

        if (! $code || $shopId <= 0) {
            return redirect()->route($returnRoute)->with('error', 'OAuth callback tidak lengkap (code / shop_id).');
        }

        $result = $api->exchangeAuthCode($code, $shopId);

        if (! $result) {
            $detail = $api->formatOAuthErrorForUser($api->getLastOAuthError());
            $message = 'Gagal menukar kode OAuth Shopee (CORENATION ADMIN).';
            if ($detail) {
                $message .= ' '.$detail;
            }

            return redirect()->route(ShopeeAdminOAuthAuthorize::pullReturnRoute())->with('error', $message);
        }

        return redirect()
            ->route(ShopeeAdminOAuthAuthorize::pullReturnRoute())
            ->with('success', 'Toko Shopee #'.$shopId.' diotorisasi. Token disimpan per shop — Indonesia (SHOPEE_ADMIN_PRIMARY_SHOP_ID) tetap dipakai Ads/Stock bila dikonfigurasi.');
    }
}