<?php

namespace App\Support;

use App\Models\Omnichannel;
use App\Models\ShopeeAds;
use App\Models\ShopeeStock;
use App\Models\User;
use Illuminate\Http\Request;

final class ShopeeAdminOAuthAuthorize
{
    public const SESSION_RETURN_ROUTE = 'shopee_admin_oauth_return';

    /** @var array<string, string> */
    public const RETURN_QUERY_TO_ROUTE = [
        'omnichannel' => 'omnichannel.index',
        'shopee-ads' => 'shopee-ads.index',
        'shopee-sync' => 'shopee.sync.index',
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
            || $user->can(ShopeeAds::getPermissions()['view'])
            || $user->can(ShopeeStock::getPermissions()['sync']);
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
        return session()->pull(self::SESSION_RETURN_ROUTE, 'omnichannel.index');
    }
}