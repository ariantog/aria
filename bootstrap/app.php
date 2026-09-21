<?php

use App\Exceptions\InsufficientWarehouseStockException;
use App\Http\Middleware\HandleAppearance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'font_size', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'jubelio/webhook/order',
            'jubelio/webhook/return',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            AddLinkHeadersForPreloadedAssets::class,
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        ]);

        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'prevent.duplicate' => \App\Http\Middleware\PreventDuplicateSubmission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // QueryException messages embed full SQL bindings (e.g. base64 session payloads).
        $exceptions->report(function (QueryException $e) {
            $previous = $e->getPrevious();
            Log::error('Database query failed', [
                'connection' => $e->getConnectionName(),
                'code' => $e->getCode(),
                'message' => $previous
                    ? $previous->getMessage()
                    : Str::before($e->getMessage(), ' (Connection:'),
                'sql' => Str::limit($e->getSql(), 500),
            ]);
        })->stop();

        $exceptions->dontReport([InsufficientWarehouseStockException::class]);

        $exceptions->renderable(function (InsufficientWarehouseStockException $e, $request) {
            return $e->toResponse($request);
        });
    })->create();
