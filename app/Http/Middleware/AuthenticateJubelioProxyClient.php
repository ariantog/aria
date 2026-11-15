<?php

namespace App\Http\Middleware;

use App\Models\JubelioProxyClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJubelioProxyClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        if ($plain === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing bearer token.',
                'status' => 401,
            ], 401);
        }

        $hash = JubelioProxyClient::hashToken($plain);
        $client = JubelioProxyClient::query()
            ->where('token_hash', $hash)
            ->first();

        if ($client === null || ! $client->isUsable()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid or revoked token.',
                'status' => 401,
            ], 401);
        }

        $request->attributes->set('jubelio_proxy_client', $client);

        return $next($request);
    }
}