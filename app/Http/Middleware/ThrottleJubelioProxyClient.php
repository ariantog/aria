<?php

namespace App\Http\Middleware;

use App\Models\JubelioProxyClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleJubelioProxyClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('jubelio_proxy_client');
        if (! $client instanceof JubelioProxyClient) {
            return $next($request);
        }

        $key = 'jbp:'.$client->id;
        $max = $client->effectiveRateLimit();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return response()->json([
                'ok' => false,
                'error' => 'Rate limit exceeded.',
                'status' => 429,
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}