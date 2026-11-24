<?php

namespace App\Http\Controllers;

use App\Jobs\PersistShopeePushInboxJob;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Shopee Open Platform Push Mechanism callback (verify + optional inbox; does not post transactions).
 */
class ShopeePushController extends Controller
{
    public function __invoke(
        Request $request,
        ShopeePushSignatureVerifier $verifier,
    ): Response
    {
        $rawBody = $request->getContent();
        $authHeader = $request->header('Authorization');

        $verify = $verifier->verifyWithCandidates($request, $rawBody, $authHeader);
        $callbackUrl = $verify['matched_url'] ?? $verifier->resolveCallbackUrl($request);

        if (! $verify['ok']) {
            Log::warning('Shopee push rejected — Authorization mismatch', [
                'candidate_urls' => $verify['candidate_urls'],
                'hint' => $verifier->partnerKey() === ''
                    ? 'Set SHOPEE_PUSH_PARTNER_KEY to Live Push Partner Key.'
                    : 'Set SHOPEE_PUSH_CALLBACK_URL to the exact Console callback URL.',
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $pushCode = isset($payload['code']) ? (int) $payload['code'] : null;
        $ordersn = $this->resolveOrderSn($data);

        Log::info('Shopee push accepted', [
            'code' => $pushCode,
            'ordersn' => $ordersn,
        ]);

        PersistShopeePushInboxJob::dispatchAfterResponse($payload, true, $callbackUrl);

        return response('', Response::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOrderSn(array $data): ?string
    {
        if (isset($data['ordersn']) && $data['ordersn'] !== '') {
            return (string) $data['ordersn'];
        }

        if (isset($data['order_sn']) && $data['order_sn'] !== '') {
            return (string) $data['order_sn'];
        }

        return null;
    }
}