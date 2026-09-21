<?php

namespace App\Http\Middleware;

use App\Services\UserPreferenceService;
use App\Support\UserPreferenceRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preferences = app(UserPreferenceService::class);
        $appearance = $request->cookie('appearance') ?? 'system';
        $fontSize = $request->cookie('font_size') ?? 'default';

        if ($request->user() && Schema::hasTable('user_preferences')) {
            $appearance = $preferences->appearanceFor($request->user());
            $fontSize = $preferences->fontSizeFor($request->user());
        }

        View::share('appearance', $appearance);
        View::share('fontSize', $fontSize);
        View::share('fontSizePixels', UserPreferenceRegistry::fontSizePixels()[$fontSize] ?? '14px');

        return $next($request);
    }
}
