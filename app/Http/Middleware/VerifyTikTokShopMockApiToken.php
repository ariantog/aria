<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTikTokShopMockApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('tiktok_shop.driver') !== 'mock') {
            return response()->json([
                'code' => 40301,
                'message' => 'Mock API disabled (TIKTOK_SHOP_DRIVER is not mock).',
                'data' => null,
            ], 403);
        }

        $expected = (string) config('tiktok_shop.mock_api_token');
        $provided = (string) ($request->header('x-tts-mock-token') ?? $request->query('mock_token', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'code' => 40101,
                'message' => 'Invalid mock API token.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
