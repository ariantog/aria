<?php

namespace App\Http\Middleware;

use App\Models\ReadonlyExport;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with export-readonly (Grok bot) may only use GET/HEAD — never mutate data via the UI or API.
 */
class EnforceReadonlyExportAccount
{
    /** @var list<string> */
    private const ALLOWED_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** @var list<string> */
    private const AUTH_EXEMPT_PREFIXES = [
        'login',
        'logout',
        'two-factor',
        'user/confirm-password',
        'sanctum/csrf-cookie',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || User::isSuperadmin($user)) {
            return $next($request);
        }

        if (! $user->can(ReadonlyExport::getPermissions()['export'])) {
            return $next($request);
        }

        if ($this->isAuthExempt($request)) {
            return $next($request);
        }

        if (! in_array(strtoupper($request->method()), self::ALLOWED_METHODS, true)) {
            abort(403, 'Read-only export account cannot mutate data.');
        }

        $path = trim($request->path(), '/');
        if (preg_match('#(^|/)(edit|create)$#', $path) === 1) {
            abort(403, 'Read-only export account cannot open edit forms.');
        }

        return $next($request);
    }

    private function isAuthExempt(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (self::AUTH_EXEMPT_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
