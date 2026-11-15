<?php

namespace App\Http\Controllers;

use App\Models\JubelioProxyClient;
use App\Services\Jubelio\JubelioProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JubelioProxyController extends Controller
{
    public function __construct(
        private JubelioProxyService $proxyService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api' => ['required', 'string', 'max:64'],
            'params' => ['sometimes', 'array'],
        ]);

        /** @var JubelioProxyClient $client */
        $client = $request->attributes->get('jubelio_proxy_client');

        $result = $this->proxyService->invoke(
            $client,
            (string) $validated['api'],
            $validated['params'] ?? [],
        );

        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status >= 400 ? $status : 200);
    }

    public function show(Request $request, string $api): JsonResponse
    {
        /** @var JubelioProxyClient $client */
        $client = $request->attributes->get('jubelio_proxy_client');

        $params = $request->query();
        unset($params['api']);

        $result = $this->proxyService->invoke($client, $api, $params);

        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status >= 400 ? $status : 200);
    }
}