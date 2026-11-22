<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Shopee Open Platform Live Redirect URL rules (Console validation).
 */
final class ShopeeOAuthRedirect
{
    public const DEFAULT_ARIA3_ADMIN_CALLBACK = 'https://aria3.corenationactive.com/shopee-admin/oauth/callback';

    public static function defaultAdminCallbackUrl(): string
    {
        $fromApp = rtrim((string) config('app.url'), '/').'/shopee-admin/oauth/callback';

        return $fromApp !== '/shopee-admin/oauth/callback' ? $fromApp : self::DEFAULT_ARIA3_ADMIN_CALLBACK;
    }

    public static function warnIfConsoleRejects(string $redirect): void
    {
        if ($redirect === '') {
            return;
        }

        if (stripos($redirect, 'bot') !== false) {
            Log::warning('SHOPEE_ADMIN_REDIRECT_URL contains "bot" — Shopee Console rejects it; use the Laravel callback on APP_URL (/shopee-admin/oauth/callback).');
        }

        if (stripos($redirect, '.php') !== false) {
            Log::warning('SHOPEE_ADMIN_REDIRECT_URL contains ".php" — Shopee Console rejects it; use the Laravel callback on APP_URL (/shopee-admin/oauth/callback).');
        }
    }
}