<?php

namespace App\Services\TikTokShop;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * In-memory mock TikTok Shop chat state (file/array cache — no DB tables).
 */
class TikTokShopMockDataStore
{
    private const CACHE_KEY = 'tiktok_shop.mock_chat.v1';

    public const SENDER_BUYER = 'BUYER';

    public const SENDER_SELLER = 'SELLER';

    public const SENDER_SYSTEM = 'SYSTEM';

    /**
     * @return array{conversations: array<string, array<string, mixed>>, messages: array<string, list<array<string, mixed>>>}
     */
    public function state(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['conversations'], $cached['messages'])) {
            return $cached;
        }

        return $this->reset();
    }

    /**
     * @return array{conversations: array<string, array<string, mixed>>, messages: array<string, list<array<string, mixed>>>}
     */
    public function reset(): array
    {
        $state = $this->buildDemoState();
        Cache::forever(self::CACHE_KEY, $state);

        return $state;
    }

    /**
     * @param  array{conversations: array<string, array<string, mixed>>, messages: array<string, list<array<string, mixed>>>}  $state
     */
    public function save(array $state): void
    {
        Cache::forever(self::CACHE_KEY, $state);
    }

    /**
     * @return array{conversations: array<string, array<string, mixed>>, messages: array<string, list<array<string, mixed>>>}
     */
    private function buildDemoState(): array
    {
        $now = Carbon::now();

        $conversations = [];
        $messages = [];

        $this->seedConversation(
            $conversations,
            $messages,
            'conv_7123456789012345001',
            'Sari Wijaya',
            '5790123456789012345',
            [
                ['msg_7001_a', self::SENDER_BUYER, 'Kak, ukuran M ready nggak untuk RUNNING SHIRT hitam?', $now->copy()->subHours(2)],
                ['msg_7001_b', self::SENDER_SELLER, 'Ready kak, stok Pusat masih ada. Mau di-reserve?', $now->copy()->subHours(2)->addMinutes(8)],
                ['msg_7001_c', self::SENDER_BUYER, 'Iya reserve ya, nanti saya checkout malam ini.', $now->copy()->subMinutes(45)],
            ],
        );

        $this->seedConversation(
            $conversations,
            $messages,
            'conv_7123456789012345002',
            'Budi Santoso',
            '5790123456789012346',
            [
                ['msg_7002_a', self::SENDER_BUYER, 'Pesanan saya sudah dikirim belum? Invoice 5790123456789012346', $now->copy()->subHours(5)],
                ['msg_7002_b', self::SENDER_SYSTEM, 'Buyer opened chat from order detail.', $now->copy()->subHours(5)->addMinute()],
            ],
        );

        $this->seedConversation(
            $conversations,
            $messages,
            'conv_7123456789012345003',
            'Dewi Lestari',
            null,
            [
                ['msg_7003_a', self::SENDER_BUYER, 'Bisa tukar size L ke XL?', $now->copy()->subDay()],
            ],
        );

        return [
            'conversations' => $conversations,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $conversations
     * @param  array<string, list<array<string, mixed>>>  $messages
     * @param  list<array{0: string, 1: string, 2: string, 3: Carbon}>  $lines
     */
    private function seedConversation(
        array &$conversations,
        array &$messages,
        string $conversationId,
        string $nickname,
        ?string $orderId,
        array $lines,
    ): void {
        $thread = [];
        $lastText = null;
        $lastAt = null;
        $unread = 0;

        foreach ($lines as [$messageId, $role, $content, $sentAt]) {
            $thread[] = $this->messageRow($conversationId, $messageId, $role, $content, $sentAt, null);
            $lastText = $content;
            $lastAt = $sentAt;
            if ($role === self::SENDER_BUYER) {
                $unread++;
            }
        }

        $messages[$conversationId] = $thread;
        $conversations[$conversationId] = $this->conversationRow(
            $conversationId,
            $nickname,
            $orderId,
            $unread,
            $lastText,
            $lastAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function conversationRow(
        string $conversationId,
        string $nickname,
        ?string $orderId,
        int $unread,
        ?string $lastText,
        ?Carbon $lastAt,
    ): array {
        return [
            'conversation_id' => $conversationId,
            'buyer_nickname' => $nickname,
            'buyer_avatar_url' => null,
            'order_id' => $orderId,
            'unread_count' => $unread,
            'last_message_text' => $lastText !== null ? mb_substr($lastText, 0, 500) : null,
            'last_message_at' => $lastAt?->getTimestamp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messageRow(
        string $conversationId,
        string $messageId,
        string $senderRole,
        string $content,
        Carbon $sentAt,
        ?int $ariaUserId,
    ): array {
        return [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_role' => $senderRole,
            'content' => $content,
            'create_time' => $sentAt->getTimestamp(),
            'aria_user_id' => $ariaUserId,
        ];
    }

    public function newMessageId(): string
    {
        return 'msg_'.Str::lower(Str::random(16));
    }
}
