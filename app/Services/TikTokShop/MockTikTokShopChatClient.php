<?php

namespace App\Services\TikTokShop;

use App\Contracts\TikTokShop\TikTokShopChatClient;
use App\Models\TikTokShopConversation;
use App\Models\TikTokShopMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class MockTikTokShopChatClient implements TikTokShopChatClient
{
    public function __construct(
        private TikTokShopMockChatSeeder $seeder,
        private TikTokShopChatPayloadBuilder $payload,
    ) {}

    public function isMock(): bool
    {
        return true;
    }

    public function listConversations(int $pageSize = 20, ?string $pageToken = null): array
    {
        $this->seeder->seedIfEmpty();

        $offset = max(0, (int) $pageToken);
        $query = TikTokShopConversation::query()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($pageSize)->get();

        $next = $offset + $rows->count() < $total
            ? (string) ($offset + $pageSize)
            : null;

        return [
            'conversations' => $rows->map(fn (TikTokShopConversation $c) => $this->payload->conversation($c))->values()->all(),
            'total' => $total,
            'next_page_token' => $next,
        ];
    }

    public function getConversation(string $externalConversationId): ?array
    {
        $this->seeder->seedIfEmpty();

        $row = TikTokShopConversation::query()
            ->where('external_conversation_id', $externalConversationId)
            ->first();

        return $row ? $this->payload->conversation($row) : null;
    }

    public function listMessages(string $externalConversationId, int $pageSize = 50, ?string $pageToken = null): array
    {
        $conversation = $this->findConversationOrFail($externalConversationId);

        $offset = max(0, (int) $pageToken);
        $query = TikTokShopMessage::query()
            ->where('tiktok_shop_conversation_id', $conversation->id)
            ->with('conversation')
            ->orderBy('sent_at')
            ->orderBy('id');

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($pageSize)->get();

        $next = $offset + $rows->count() < $total
            ? (string) ($offset + $pageSize)
            : null;

        return [
            'messages' => $rows->map(fn (TikTokShopMessage $m) => $this->payload->message($m))->values()->all(),
            'total' => $total,
            'next_page_token' => $next,
        ];
    }

    public function sendSellerMessage(string $externalConversationId, string $content, ?User $sender): array
    {
        $conversation = $this->findConversationOrFail($externalConversationId);
        $trimmed = trim($content);

        if ($trimmed === '') {
            throw new RuntimeException('Pesan tidak boleh kosong.');
        }

        $sentAt = Carbon::now();
        $message = TikTokShopMessage::query()->create([
            'tiktok_shop_conversation_id' => $conversation->id,
            'external_message_id' => 'msg_'.Str::lower(Str::random(16)),
            'sender_role' => TikTokShopMessage::SENDER_SELLER,
            'content' => $trimmed,
            'aria_user_id' => $sender?->id,
            'sent_at' => $sentAt,
        ]);

        $conversation->forceFill([
            'last_message_text' => mb_substr($trimmed, 0, 500),
            'last_message_at' => $sentAt,
            'unread_count' => 0,
        ])->save();

        $message->setRelation('conversation', $conversation);

        return $this->payload->message($message);
    }

    /**
     * Mock-only: simulate an inbound buyer message (webhook / polling substitute).
     *
     * @return array<string, mixed>
     */
    public function simulateBuyerMessage(string $externalConversationId, string $content): array
    {
        $conversation = $this->findConversationOrFail($externalConversationId);
        $trimmed = trim($content);

        if ($trimmed === '') {
            throw new RuntimeException('Pesan buyer simulasi tidak boleh kosong.');
        }

        $sentAt = Carbon::now();
        $message = TikTokShopMessage::query()->create([
            'tiktok_shop_conversation_id' => $conversation->id,
            'external_message_id' => 'msg_'.Str::lower(Str::random(16)),
            'sender_role' => TikTokShopMessage::SENDER_BUYER,
            'content' => $trimmed,
            'aria_user_id' => null,
            'sent_at' => $sentAt,
        ]);

        $conversation->forceFill([
            'last_message_text' => mb_substr($trimmed, 0, 500),
            'last_message_at' => $sentAt,
            'unread_count' => (int) $conversation->unread_count + 1,
        ])->save();

        $message->setRelation('conversation', $conversation);

        return $this->payload->message($message);
    }

    public function markConversationRead(string $externalConversationId): void
    {
        $conversation = $this->findConversationOrFail($externalConversationId);
        $conversation->forceFill(['unread_count' => 0])->save();
    }

    private function findConversationOrFail(string $externalConversationId): TikTokShopConversation
    {
        $this->seeder->seedIfEmpty();

        $conversation = TikTokShopConversation::query()
            ->where('external_conversation_id', $externalConversationId)
            ->first();

        if ($conversation === null) {
            throw new RuntimeException('Conversation TikTok tidak ditemukan.');
        }

        return $conversation;
    }
}
