<?php

namespace App\Services\TikTokShop;

use App\Contracts\TikTokShop\TikTokShopChatClient;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class MockTikTokShopChatClient implements TikTokShopChatClient
{
    public function __construct(
        private TikTokShopMockDataStore $store,
        private TikTokShopChatPayloadBuilder $payload,
    ) {}

    public function isMock(): bool
    {
        return true;
    }

    public function listConversations(int $pageSize = 20, ?string $pageToken = null): array
    {
        $state = $this->store->state();
        $rows = collect($state['conversations'])
            ->sortByDesc(fn (array $c) => $c['last_message_at'] ?? 0)
            ->values();

        $offset = max(0, (int) $pageToken);
        $page = $rows->slice($offset, $pageSize)->values();
        $total = $rows->count();
        $next = $offset + $page->count() < $total ? (string) ($offset + $pageSize) : null;

        return [
            'conversations' => $page->map(fn (array $c) => $this->payload->conversation($c))->all(),
            'total' => $total,
            'next_page_token' => $next,
        ];
    }

    public function getConversation(string $externalConversationId): ?array
    {
        $state = $this->store->state();
        $row = $state['conversations'][$externalConversationId] ?? null;

        return $row ? $this->payload->conversation($row) : null;
    }

    public function listMessages(string $externalConversationId, int $pageSize = 50, ?string $pageToken = null): array
    {
        $this->findConversationOrFail($externalConversationId);
        $state = $this->store->state();
        $rows = collect($state['messages'][$externalConversationId] ?? [])
            ->sortBy('create_time')
            ->values();

        $offset = max(0, (int) $pageToken);
        $page = $rows->slice($offset, $pageSize)->values();
        $total = $rows->count();
        $next = $offset + $page->count() < $total ? (string) ($offset + $pageSize) : null;

        return [
            'messages' => $page->map(fn (array $m) => $this->payload->message($m))->all(),
            'total' => $total,
            'next_page_token' => $next,
        ];
    }

    public function sendSellerMessage(string $externalConversationId, string $content, ?User $sender): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new RuntimeException('Pesan tidak boleh kosong.');
        }

        $sentAt = Carbon::now();
        $message = $this->store->messageRow(
            $externalConversationId,
            $this->store->newMessageId(),
            TikTokShopMockDataStore::SENDER_SELLER,
            $trimmed,
            $sentAt,
            $sender?->id,
        );

        $this->appendMessage($externalConversationId, $message, unreadAfter: 0);

        return $this->payload->message($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function simulateBuyerMessage(string $externalConversationId, string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new RuntimeException('Pesan buyer simulasi tidak boleh kosong.');
        }

        $conversation = $this->findConversationOrFail($externalConversationId);
        $unread = (int) ($conversation['unread_count'] ?? 0) + 1;

        $sentAt = Carbon::now();
        $message = $this->store->messageRow(
            $externalConversationId,
            $this->store->newMessageId(),
            TikTokShopMockDataStore::SENDER_BUYER,
            $trimmed,
            $sentAt,
            null,
        );

        $this->appendMessage($externalConversationId, $message, unreadAfter: $unread);

        return $this->payload->message($message);
    }

    public function markConversationRead(string $externalConversationId): void
    {
        $state = $this->store->state();
        if (! isset($state['conversations'][$externalConversationId])) {
            return;
        }

        $state['conversations'][$externalConversationId]['unread_count'] = 0;
        $this->store->save($state);
    }

    public function resetDemo(): void
    {
        $this->store->reset();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function appendMessage(string $externalConversationId, array $message, int $unreadAfter): void
    {
        $state = $this->store->state();
        $conversation = $state['conversations'][$externalConversationId] ?? null;

        if ($conversation === null) {
            throw new RuntimeException('Conversation TikTok tidak ditemukan.');
        }

        $state['messages'][$externalConversationId] ??= [];
        $state['messages'][$externalConversationId][] = $message;

        $conversation['last_message_text'] = mb_substr((string) $message['content'], 0, 500);
        $conversation['last_message_at'] = $message['create_time'];
        $conversation['unread_count'] = $unreadAfter;
        $state['conversations'][$externalConversationId] = $conversation;

        $this->store->save($state);
    }

    /**
     * @return array<string, mixed>
     */
    private function findConversationOrFail(string $externalConversationId): array
    {
        $state = $this->store->state();
        $conversation = $state['conversations'][$externalConversationId] ?? null;

        if ($conversation === null) {
            throw new RuntimeException('Conversation TikTok tidak ditemukan.');
        }

        return $conversation;
    }
}
