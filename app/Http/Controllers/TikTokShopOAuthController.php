<?php

namespace App\Http\Controllers;

use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Support\TikTokShopOAuthAuthorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TikTokShopOAuthController extends Controller
{
    public function authorizeShop(Request $request, TikTokShopOAuthService $oauth): RedirectResponse
    {
        TikTokShopOAuthAuthorize::abortUnlessMayAuthorize($request);

        if (! $oauth->isConfigured()) {
            $return = (string) $request->query('return', 'omnichannel');
            $route = TikTokShopOAuthAuthorize::RETURN_QUERY_TO_ROUTE[$return] ?? TikTokShopOAuthAuthorize::RETURN_QUERY_TO_ROUTE['omnichannel'];

            return redirect()
                ->route($route, ['tab' => 'connections'])
                ->with('error', 'TIKTOK_SHOP_APP_KEY / TIKTOK_SHOP_APP_SECRET belum dikonfigurasi di .env.');
        }

        if (! filled(config('tiktok_shop.service_id'))) {
            return redirect()
                ->route('omnichannel.settings.index', ['tab' => 'connections'])
                ->with('error', 'TIKTOK_SHOP_SERVICE_ID belum dikonfigurasi (Partner Center → App & Service).');
        }

        TikTokShopOAuthAuthorize::rememberReturnRoute($request);

        $state = bin2hex(random_bytes(8));
        session(['tiktok_shop_oauth_state' => $state]);

        return redirect()->away($oauth->buildAuthorizeUrl($state));
    }

    public function oauthCallback(Request $request, TikTokShopOAuthService $oauth): RedirectResponse
    {
        $code = $request->query('code');
        $error = $request->query('error');
        $returnRoute = session(TikTokShopOAuthAuthorize::SESSION_RETURN_ROUTE, 'omnichannel.settings.index');

        if ($error) {
            return redirect()
                ->route($returnRoute, ['tab' => 'connections'])
                ->with('error', 'TikTok Shop OAuth ditolak: '.(string) $error);
        }

        if (! filled($code) || strtolower((string) $code) === 'null') {
            return redirect()
                ->route($returnRoute, ['tab' => 'connections'])
                ->with('error', 'OAuth callback tidak lengkap (code).');
        }

        $result = $oauth->exchangeAuthCode((string) $code);

        if (! $result) {
            $detail = $oauth->getLastOAuthError();
            $message = 'Gagal menukar kode OAuth TikTok Shop.';
            if ($detail) {
                $message .= ' '.$detail;
            }

            return redirect()
                ->route(TikTokShopOAuthAuthorize::pullReturnRoute(), ['tab' => 'connections'])
                ->with('error', $message);
        }

        return redirect()
            ->route(TikTokShopOAuthAuthorize::pullReturnRoute(), ['tab' => 'connections'])
            ->with('success', 'TikTok Shop berhasil diotorisasi — token disimpan untuk omnichannel order detail dan Grok readonly API.');
    }
}