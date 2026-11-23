<?php

namespace App\Http\Controllers\Readonly;

use App\Http\Controllers\Controller;
use App\Models\ReadonlyExport;
use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeOAuthExpiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Grok read-only API: current Shopee shop access token (CORENATION ADMIN OAuth).
 *
 * Never exposes refresh_token, partner keys, or client secrets.
 * May persist a refreshed access token to settings (same path as cron / getAccessToken).
 */
class ReadonlyShopeeOAuthController extends Controller
{
    public function show(ShopeeAdminOpenApiService $shopeeAdmin): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        if (! $shopeeAdmin->isConfigured()) {
            return response()->json([
                'error' => 'shopee_not_configured',
                'message' => 'Shopee admin Open Platform credentials are not configured on this server.',
            ], 503);
        }

        $oauth = $this->storedOAuthPayload();

        if (ShopeeOAuthExpiry::requiresReauthorization($oauth)) {
            return response()->json([
                'error' => 'shopee_not_connected',
                'message' => 'Shop OAuth is missing or requires browser re-authorization.',
                'configured' => true,
                'shop_id' => $oauth['shop_id'] ?? null,
            ], 401);
        }

        $accessToken = $shopeeAdmin->getAccessToken();
        $oauth = $this->storedOAuthPayload();

        if (! $accessToken || (int) ($oauth['shop_id'] ?? 0) <= 0) {
            return response()->json([
                'error' => 'shopee_token_unavailable',
                'message' => 'Unable to obtain a valid Shopee access token. Try again later or re-authorize in Aria.',
                'configured' => true,
                'shop_id' => $oauth['shop_id'] ?? null,
            ], 503);
        }

        $expiresAt = ShopeeOAuthExpiry::accessExpiresAt($oauth);

        return response()->json([
            'access_token' => $accessToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_at_unix' => $expiresAt?->getTimestamp(),
            'shop_id' => (int) $oauth['shop_id'],
            'partner_app' => 'shopee_admin',
            'oauth_setting_slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedOAuthPayload(): array
    {
        $value = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }
}