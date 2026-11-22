<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestockGrokApplyEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('readonly_export.restock_grok_apply_enabled', false)) {
            abort(403, 'Grok restock apply is disabled on this server. Produce a flat file for manual upload instead.');
        }

        return $next($request);
    }
}