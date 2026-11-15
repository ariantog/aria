<?php

namespace App\Services\Jubelio;

use App\Models\JubelioProxyClient;
use App\Models\JubelioProxyRequestLog;
use App\Services\JubelioService;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class JubelioProxyService
{
    public function __construct(
        private JubelioService $jubelioService,
        private JubelioProxyResponseSanitizer $sanitizer,
    ) {}

  /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function invoke(JubelioProxyClient $client, string $apiName, array $params = []): array
    {
        $started = hrtime(true);

        $definition = config('jubelio_proxy.apis', [])[$apiName] ?? null;

        if (! is_array($definition)) {
            return $this->fail($client, $apiName, $params, $started, 'Unknown API name.', 403);
        }

        if (($definition['enabled'] ?? true) === false) {
            return $this->fail($client, $apiName, $params, $started, 'API is disabled.', 403);
        }

        if (! $client->allowsApi($apiName)) {
            return $this->fail($client, $apiName, $params, $started, 'API not allowed for this client.', 403);
        }

        $access = (string) ($definition['access'] ?? 'read');
        if ($access === 'write') {
            if (! config('jubelio_proxy.allow_write', false) || ! $client->write_enabled) {
                return $this->fail($client, $apiName, $params, $started, 'Write APIs are disabled for this client.', 403);
            }
        }

        $method = strtoupper((string) ($definition['method'] ?? 'GET'));
        if ($access === 'read' && $method !== 'GET') {
            return $this->fail($client, $apiName, $params, $started, 'Only read APIs are enabled in phase 1.', 403);
        }

        try {
            [$url, $query, $body] = $this->buildRequest($definition, $params);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($client, $apiName, $params, $started, $e->getMessage(), 422);
        }

        $response = $this->callJubelio($method, $url, $query, $body);

        if ($response === null) {
            return $this->fail($client, $apiName, $params, $started, 'Jubelio request failed (auth or transport).', 502);
        }

        $status = $response->status();
        $json = $response->json();

        if (! is_array($json)) {
            $json = ['raw' => Str::limit($response->body(), 2000)];
        }

        $containsPii = (bool) ($definition['contains_pii'] ?? false);
        $containsFinance = (bool) ($definition['contains_finance'] ?? false);
        $sanitized = $this->sanitizer->sanitize($json, $client, $containsPii, $containsFinance);

        $this->log($client, $apiName, $params, $status, $started, null);
        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return [
            'ok' => $response->successful(),
            'api' => $apiName,
            'status' => $status,
            'data' => $sanitized,
            'fetched_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ];
    }

  /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildRequest(array $definition, array $params): array
    {
        $url = (string) $definition['url'];
        $pathParams = $definition['path_params'] ?? [];

        foreach ($pathParams as $key) {
            if (! array_key_exists($key, $params)) {
                throw new \InvalidArgumentException("Missing path param: {$key}");
            }

            $value = $params[$key];
            if (! is_scalar($value) || $value === '') {
                throw new \InvalidArgumentException("Invalid path param: {$key}");
            }

            $url = str_replace('{'.$key.'}', rawurlencode((string) $value), $url);
            unset($params[$key]);
        }

        $allowedQuery = $definition['query_params'] ?? [];
        $allowedBody = $definition['body_params'] ?? [];
        $allowed = array_merge($allowedQuery, $allowedBody, array_keys($definition['default_query'] ?? []));

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($allowed !== [] && ! in_array((string) $key, $allowed, true)) {
                throw new \InvalidArgumentException("Param not allowed: {$key}");
            }
            if ($value !== null && $value !== '') {
                $filtered[(string) $key] = $value;
            }
        }

        $query = array_merge($definition['default_query'] ?? [], $filtered);

        if (isset($query['pageSize'])) {
            $max = (int) config('jubelio_proxy.max_page_size', 200);
            $query['pageSize'] = min((int) $query['pageSize'], $max);
        }

        $method = strtoupper((string) ($definition['method'] ?? 'GET'));
        if ($method === 'GET') {
            return [$url, $query, []];
        }

        return [$url, [], $query];
    }

  /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    private function callJubelio(string $method, string $url, array $query, array $body): ?Response
    {
        return match ($method) {
            'GET' => $this->jubelioService->get($url, $query),
            'POST' => $this->jubelioService->post($url, $body),
            default => null,
        };
    }

  /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function fail(
        JubelioProxyClient $client,
        string $apiName,
        array $params,
        int $startedHr,
        string $error,
        int $httpStatus,
    ): array {
        $this->log($client, $apiName, $params, null, $startedHr, $error);

        return [
            'ok' => false,
            'error' => $error,
            'status' => $httpStatus,
            'api' => $apiName,
        ];
    }

  /**
     * @param  array<string, mixed>  $params
     */
    private function log(
        JubelioProxyClient $client,
        string $apiName,
        array $params,
        ?int $jubelioStatus,
        int $startedHr,
        ?string $error,
    ): void {
        $durationMs = (int) round((hrtime(true) - $startedHr) / 1_000_000);

        JubelioProxyRequestLog::query()->create([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'api_name' => $apiName,
            'params_redacted' => $this->sanitizer->redactParams($params),
            'jubelio_status' => $jubelioStatus,
            'duration_ms' => $durationMs,
            'error' => $error !== null ? Str::limit($error, 250) : null,
            'requested_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

  /**
     * @return list<string>
     */
    public static function readApiNames(): array
    {
        $apis = config('jubelio_proxy.apis', []);

        return collect($apis)
            ->filter(fn (array $def) => ($def['access'] ?? 'read') === 'read' && ($def['enabled'] ?? true))
            ->keys()
            ->sort()
            ->values()
            ->all();
    }
}