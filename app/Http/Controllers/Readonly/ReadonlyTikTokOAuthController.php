<?php

namespace App\Http\Controllers\Readonly;

use App\Http\Controllers\Controller;
use App\Models\ReadonlyExport;
use App\Models\Setting;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Support\TikTokShopOAuthExpiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Grok read-only API: current TikTok Shop access token.
 */
class ReadonlyTikTokOAuthController extends Controller
{
    public function show(TikTokShopOAuthService $tikTokOAuth): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['tiktok_oauth']);

        if (! $tikTokOAuth->isConfigured()) {
            return response()->json([
                'error' => 'tiktok_not_configured',
                'message' => 'TikTok Shop app credentials are not configured on this server.',
            ], 503);
        }

        $oauth = $this->storedOAuthPayload();

        if (TikTokShopOAuthExpiry::requiresReauthorization($oauth)) {
            $envToken = trim((string) config('tiktok_shop.access_token', ''));
            if ($envToken !== '') {
                return response()->json([
                    'access_token' => $envToken,
                    'expires_at' => null,
                    'expires_at_unix' => null,
                    'partner_app' => 'tiktok_shop',
                    'oauth_setting_slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG,
                    'source' => 'env_fallback',
                ]);
            }

            return response()->json([
                'error' => 'tiktok_not_connected',
                'message' => 'Shop OAuth is missing or requires browser re-authorization in Aria.',
                'configured' => true,
            ], 401);
        }

        $accessToken = $tikTokOAuth->getAccessToken();
        $oauth = $this->storedOAuthPayload();

        if (! $accessToken) {
            return response()->json([
                'error' => 'tiktok_token_unavailable',
                'message' => 'Unable to obtain a valid TikTok access token. Try again later or re-authorize in Aria.',
                'configured' => true,
            ], 503);
        }

        $expiresAt = TikTokShopOAuthExpiry::accessExpiresAt($oauth);

        return response()->json([
            'access_token' => $accessToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_at_unix' => $expiresAt?->getTimestamp(),
            'partner_app' => 'tiktok_shop',
            'oauth_setting_slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedOAuthPayload(): array
    {
        $value = Setting::getValue(TikTokShopOAuthService::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }
}