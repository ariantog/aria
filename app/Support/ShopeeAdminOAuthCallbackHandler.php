<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShopeeAdminOAuthCallbackHandler
{
    public function handle(Request $request, ShopeeAdminOpenApiService $api, string $returnRoute): RedirectResponse
    {
        $query = $request->query();
        $code = isset($query['code']) ? (string) $query['code'] : '';
        $shopId = isset($query['shop_id']) ? (int) $query['shop_id'] : 0;
        $mainAccountId = isset($query['main_account_id']) ? (int) $query['main_account_id'] : 0;

        $api->recordOAuthCallbackQuery($query);

        if ($code === '') {
            return redirect()
                ->route($returnRoute)
                ->with('error', 'OAuth callback tanpa code — coba Authorize lagi.');
        }

        if ($shopId <= 0 && $mainAccountId <= 0) {
            return redirect()
                ->route($returnRoute)
                ->with('error', 'OAuth callback tidak ada shop_id / main_account_id. Query tersimpan di settings — lihat Omnichannel → Connections.');
        }

        $result = $api->exchangeAuthCode(
            $code,
            $shopId > 0 ? $shopId : null,
            $mainAccountId > 0 ? $mainAccountId : null,
        );

        if ($result === null) {
            $detail = $api->formatOAuthErrorForUser($api->getLastOAuthError());
            $message = 'Gagal menukar kode OAuth Shopee (CORENATION ADMIN).';
            if ($detail) {
                $message .= ' '.$detail;
            }

            return redirect()->route($returnRoute)->with('error', $message);
        }

        $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);
        $shopIds = ShopeeAdminOAuthStore::authorizedShopIds($stored);
        $list = $stored['shop_id_list'] ?? null;

        $message = 'Shopee OAuth berhasil.';
        if ($shopIds !== []) {
            $message .= ' Shop ID: '.implode(', ', $shopIds).'.';
        }
        if (is_array($list) && $list !== []) {
            $message .= ' (shop_id_list dari Shopee: '.implode(', ', array_map('strval', $list)).')';
        }
        if ($mainAccountId > 0) {
            $message .= ' Main account '.$mainAccountId.'.';
        }
        $message .= ' Daftar lengkap ada di Omnichannel → Settings → Connections.';

        return redirect()->route($returnRoute)->with('success', $message);
    }
}