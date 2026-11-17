<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel returns HTML redirects for validation and some errors when Accept is not JSON.
 * Mobile clients and proxies may omit or override Accept; API routes should always respond as JSON.
 */
class EnsureApiRequestsPreferJson
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}