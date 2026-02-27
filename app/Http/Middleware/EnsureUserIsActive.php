<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            // If the request expects JSON (e.g. API/Inertia partial reload),
            // you might want to return 403 or redirect responsibly.
            // For standard Inertia app, redirect works.

            if ($request->routeIs('banned')) {
                return $next($request);
            }

            return redirect()->route('banned');
        }

        return $next($request);
    }
}
