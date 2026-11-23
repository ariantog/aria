<?php

namespace App\Support;

use App\Models\Omnichannel;
use App\Models\TikTokShop;
use App\Models\User;
use Illuminate\Http\Request;

final class TikTokShopOAuthAuthorize
{
    public const SESSION_RETURN_ROUTE = 'tiktok_shop_oauth_return';

    /** @var array<string, string> */
    public const RETURN_QUERY_TO_ROUTE = [
        'omnichannel' => 'omnichannel.settings.index',
        'tiktok-sync' => 'tiktok-shop.sync.index',
    ];

    public static function userMayAuthorize(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return $user->can(Omnichannel::getPermissions()['view'])
            || $user->can(Omnichannel::getPermissions()['settings'])
            || $user->can(TikTokShop::getPermissions()['view'])
            || $user->can(TikTokShop::getPermissions()['sync']);
    }

    public static function abortUnlessMayAuthorize(Request $request): void
    {
        if (! self::userMayAuthorize($request->user())) {
            abort(403);
        }
    }

    public static function rememberReturnRoute(Request $request): void
    {
        $return = (string) $request->query('return', 'omnichannel');
        $route = self::RETURN_QUERY_TO_ROUTE[$return] ?? self::RETURN_QUERY_TO_ROUTE['omnichannel'];
        session([self::SESSION_RETURN_ROUTE => $route]);
    }

    public static function pullReturnRoute(): string
    {
        return session()->pull(self::SESSION_RETURN_ROUTE, 'omnichannel.settings.index');
    }
}