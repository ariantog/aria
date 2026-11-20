<?php

namespace App\Http\Controllers;

use App\Models\Omnichannel;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopeeAdminController extends Controller
{
    public function authorizeShop(Request $request, ShopeeAdminOpenApiService $api): RedirectResponse
    {
        abort_unless(Omnichannel::userCanView($request->user()), 403);

        if (! $api->isConfigured()) {
            return redirect()
                ->route('omnichannel.index')
                ->with('error', 'SHOPEE_ADMIN_PARTNER_ID / SHOPEE_ADMIN_PARTNER_KEY belum dikonfigurasi di .env (app CORENATION ADMIN).');
        }

        return redirect()->away($api->buildAuthorizeUrl());
    }

    public function oauthCallback(Request $request, ShopeeAdminOpenApiService $api): RedirectResponse
    {
        $code = $request->query('code');
        $shopId = (int) $request->query('shop_id');

        if (! $code || $shopId <= 0) {
            return redirect()->route('omnichannel.index')->with('error', 'OAuth callback tidak lengkap (code / shop_id).');
        }

        $result = $api->exchangeAuthCode($code, $shopId);

        if (! $result) {
            $detail = $api->formatOAuthErrorForUser($api->getLastOAuthError());
            $message = 'Gagal menukar kode OAuth Shopee (CORENATION ADMIN).';
            if ($detail) {
                $message .= ' '.$detail;
            }

            return redirect()->route('omnichannel.index')->with('error', $message);
        }

        return redirect()->route('omnichannel.index')->with('success', 'Toko Shopee berhasil diotorisasi untuk Omnichannel (Admin API).');
    }
}