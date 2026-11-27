<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Optional shared secret on CDN → aria3 webhook relay forwards (Authorization: Bearer …).
 */
class MarketplaceWebhookRelayBearer
{
    public static function accepts(Request $request, string $expectedBearer): bool
    {
        $expectedBearer = trim($expectedBearer);
        if ($expectedBearer === '') {
            return true;
        }

        $authorization = trim((string) $request->header('Authorization'));
        if ($authorization === '' || ! preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals($expectedBearer, $matches[1]);
    }
}