<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the client sends X-Idempotency-Key (one UUID per submit attempt), concurrent
 * duplicates are rejected and completed submissions are replayed for a short TTL.
 */
class PreventDuplicateSubmission
{
    private const MIN_KEY_LENGTH = 8;

    private const MAX_KEY_LENGTH = 255;

    private const CACHE_TTL_MINUTES = 5;

    private const LOCK_SECONDS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('X-Idempotency-Key', ''));

        if ($key === '') {
            $key = trim((string) $request->input('_idempotency_key', ''));
        }

        if ($key === '' || strlen($key) < self::MIN_KEY_LENGTH || strlen($key) > self::MAX_KEY_LENGTH) {
            return $next($request);
        }

        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$userId}:{$key}";
        $lockKey = $cacheKey.':lock';

        if (Cache::has($cacheKey)) {
            return $this->rebuildResponse(Cache::get($cacheKey));
        }

        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return $this->duplicateInFlightResponse($request);
        }

        try {
            if (Cache::has($cacheKey)) {
                return $this->rebuildResponse(Cache::get($cacheKey));
            }

            $response = $next($request);
            $status = $response->getStatusCode();

            if ($status < 500 && $status !== 409) {
                Cache::put($cacheKey, $this->serializeResponse($response), now()->addMinutes(self::CACHE_TTL_MINUTES));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function duplicateInFlightResponse(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'This submission is already being processed. Please wait.',
            ], 409);
        }

        return back()->withInput()->withErrors([
            'submission' => 'This submission is already being processed. Please wait.',
        ]);
    }

    /**
     * @return array{status: int, headers: array<string, array<int, string>>, content: string|false}
     */
    private function serializeResponse(Response $response): array
    {
        $headers = [];
        foreach (['content-type', 'location'] as $name) {
            if ($response->headers->has($name)) {
                $headers[$name] = $response->headers->all($name);
            }
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => $headers,
            'content' => $response->getContent(),
        ];
    }

    /**
     * @param  array{status: int, headers: array<string, array<int, string>>, content: string|false}  $cached
     */
    private function rebuildResponse(array $cached): Response
    {
        $response = response($cached['content'], $cached['status']);

        foreach ($cached['headers'] as $name => $values) {
            $response->headers->set($name, $values);
        }

        return $response;
    }
}
