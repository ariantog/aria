<?php

namespace App\Http\Controllers;

use App\Contracts\TikTokShop\TikTokShopChatClient;
use App\Services\TikTokShop\MockTikTokShopChatClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Lightweight stand-in for TikTok Shop Open API customer_service endpoints (mock driver only).
 */
class TikTokShopMockApiController extends Controller
{
    public function listConversations(Request $request, TikTokShopChatClient $client): JsonResponse
    {
        return $this->wrap(function () use ($request, $client) {
            $pageSize = min(50, max(1, (int) $request->query('page_size', 20)));
            $pageToken = $request->query('page_token');

            $result = $client->listConversations($pageSize, $pageToken !== null ? (string) $pageToken : null);

            return [
                'conversations' => $result['conversations'],
                'total_count' => $result['total'],
                'next_page_token' => $result['next_page_token'] ?? '',
            ];
        });
    }

    public function listMessages(Request $request, string $conversationId, TikTokShopChatClient $client): JsonResponse
    {
        return $this->wrap(function () use ($request, $conversationId, $client) {
            $pageSize = min(100, max(1, (int) $request->query('page_size', 50)));
            $pageToken = $request->query('page_token');

            $result = $client->listMessages($conversationId, $pageSize, $pageToken !== null ? (string) $pageToken : null);

            return [
                'conversation_id' => $conversationId,
                'messages' => $result['messages'],
                'total_count' => $result['total'],
                'next_page_token' => $result['next_page_token'] ?? '',
            ];
        });
    }

    public function sendMessage(Request $request, string $conversationId, TikTokShopChatClient $client): JsonResponse
    {
        return $this->wrap(function () use ($request, $conversationId, $client) {
            $content = (string) $request->input('content', $request->input('text', ''));

            $payload = $client->sendSellerMessage($conversationId, $content, null);

            return ['message' => $payload];
        });
    }

    public function simulateBuyerMessage(Request $request, string $conversationId, TikTokShopChatClient $client): JsonResponse
    {
        return $this->wrap(function () use ($request, $conversationId, $client) {
            if (! $client instanceof MockTikTokShopChatClient) {
                throw new RuntimeException('simulate buyer hanya tersedia pada mock driver.');
            }

            $content = (string) $request->input('content', $request->input('text', ''));
            $payload = $client->simulateBuyerMessage($conversationId, $content);

            return ['message' => $payload];
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $action
     */
    private function wrap(callable $action): JsonResponse
    {
        try {
            return response()->json([
                'code' => 0,
                'message' => 'Success',
                'request_id' => 'mock_'.bin2hex(random_bytes(8)),
                'data' => $action(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'code' => 40001,
                'message' => $e->getMessage(),
                'request_id' => 'mock_'.bin2hex(random_bytes(8)),
                'data' => null,
            ], 400);
        }
    }
}
