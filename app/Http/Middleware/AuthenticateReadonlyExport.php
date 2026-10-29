<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate read-only export API via session (web) or READONLY_EXPORT_BEARER_TOKEN.
 */
class AuthenticateReadonlyExport
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $configured = (string) config('readonly_export.bearer_token', '');
        $provided = (string) $request->bearerToken();

        if ($configured !== '' && $provided !== '' && hash_equals($configured, $provided)) {
            $username = (string) config('readonly_export.username', 'grok');
            $user = User::query()->where('username', $username)->where('active', true)->first();

            if ($user !== null) {
                auth()->setUser($user);
                $request->setUserResolver(fn () => $user);

                return $next($request);
            }
        }

        abort(401, 'Unauthenticated.');
    }
}
