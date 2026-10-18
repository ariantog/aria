<?php

namespace App\Http\Controllers;

use App\Services\Lark\LarkRestockSubmitService;
use App\Services\Lark\LarkRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LarkWebhookController extends Controller
{
    public function handleEvent(Request $request, LarkRestockSubmitService $restockSubmit, LarkRosterService $roster): JsonResponse
    {
        $payload = $request->all();

        if (($payload['type'] ?? '') === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        $token = config('lark.verification_token');
        if (is_string($token) && $token !== '' && ($payload['token'] ?? null) !== $token) {
            Log::warning('Lark webhook token mismatch');

            return response()->json(['code' => 403, 'msg' => 'invalid token'], 403);
        }

        $event = $payload['event'] ?? $payload;
        $action = $event['action'] ?? null;
        if (! is_array($action)) {
            return response()->json(['code' => 0]);
        }

        $value = $action['value'] ?? [];
        if (! is_array($value)) {
            return response()->json(['code' => 0]);
        }

        if (($value['action'] ?? '') !== 'restock_apply') {
            return response()->json(['code' => 0]);
        }

        $form = $action['form_value'] ?? $action['input_value'] ?? [];
        $qty = (int) ($form['qty_restock'] ?? $value['qty_restock'] ?? 0);
        $requestToken = (string) ($value['token'] ?? '');

        $operatorOpenId = $event['operator']['open_id']
            ?? $payload['open_id']
            ?? null;
        $user = $roster->findUserByOpenId(is_string($operatorOpenId) ? $operatorOpenId : null);

        $result = $restockSubmit->applyFromCallback($requestToken, $qty, $user);

        return response()->json([
            'toast' => [
                'type' => $result['ok'] ? 'success' : 'error',
                'content' => $result['message'],
            ],
        ]);
    }
}
