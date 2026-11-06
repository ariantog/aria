<?php

namespace App\Http\Controllers;

use App\Contracts\TikTokShop\TikTokShopChatClient;
use App\Models\TikTokShop;
use App\Services\TikTokShop\MockTikTokShopChatClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class TikTokShopChatController extends Controller
{
    public function index(Request $request, TikTokShopChatClient $client): View
    {
        Gate::authorize(TikTokShop::getPermissions()['view']);

        $selectedId = (string) $request->query('conversation', '');
        $list = $client->listConversations(50, null);

        if ($selectedId === '' && $list['conversations'] !== []) {
            $selectedId = (string) $list['conversations'][0]['conversation_id'];
        }

        $messages = [];
        $activeConversation = null;

        if ($selectedId !== '') {
            $activeConversation = $client->getConversation($selectedId);
            if ($activeConversation !== null) {
                $messageList = $client->listMessages($selectedId, 100, null);
                $messages = $messageList['messages'];

                if ($client instanceof MockTikTokShopChatClient) {
                    $client->markConversationRead($selectedId);
                    $activeConversation['unread_count'] = 0;
                }
            }
        }

        $list = $client->listConversations(50, null);

        return view('tiktok-shop.chats', [
            'driver' => config('tiktok_shop.driver'),
            'isMock' => $client->isMock(),
            'conversations' => $list['conversations'],
            'activeConversationId' => $selectedId,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
            'canReply' => $request->user()?->can(TikTokShop::getPermissions()['reply']) ?? false,
            'mockApiBase' => url('/tiktok-shop/mock/open_api/v'.config('tiktok_shop.api_version').'/customer_service'),
            'mockApiTokenHint' => config('tiktok_shop.mock_api_token'),
        ]);
    }

    public function reply(Request $request, string $conversationId, TikTokShopChatClient $client): RedirectResponse|JsonResponse
    {
        Gate::authorize(TikTokShop::getPermissions()['reply']);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $payload = $client->sendSellerMessage($conversationId, $validated['content'], $request->user());
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('errorMessage', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $payload]);
        }

        return redirect()
            ->route('tiktok-shop.chats.index', ['conversation' => $conversationId])
            ->with('successMessage', 'Balasan terkirim (mock TikTok Shop).');
    }

    public function simulateBuyer(Request $request, string $conversationId, TikTokShopChatClient $client): RedirectResponse|JsonResponse
    {
        Gate::authorize(TikTokShop::getPermissions()['view']);

        if (! $client instanceof MockTikTokShopChatClient) {
            abort(404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $client->simulateBuyerMessage($conversationId, $validated['content']);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('errorMessage', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('tiktok-shop.chats.index', ['conversation' => $conversationId])
            ->with('successMessage', 'Pesan buyer simulasi ditambahkan.');
    }

    public function resetMock(TikTokShopChatClient $client): RedirectResponse
    {
        Gate::authorize(TikTokShop::getPermissions()['view']);

        if (! $client instanceof MockTikTokShopChatClient) {
            abort(404);
        }

        $client->resetDemo();

        return redirect()
            ->route('tiktok-shop.chats.index')
            ->with('successMessage', 'Data demo TikTok Shop chat di-reset.');
    }
}
