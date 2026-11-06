<?php

namespace App\Contracts\TikTokShop;

use App\Models\User;

interface TikTokShopChatClient
{
    /**
     * @return array{
     *     conversations: list<array<string, mixed>>,
     *     total: int,
     *     next_page_token: string|null,
     * }
     */
    public function listConversations(int $pageSize = 20, ?string $pageToken = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getConversation(string $externalConversationId): ?array;

    /**
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     total: int,
     *     next_page_token: string|null,
     * }
     */
    public function listMessages(string $externalConversationId, int $pageSize = 50, ?string $pageToken = null): array;

    /**
     * @return array<string, mixed>
     */
    public function sendSellerMessage(string $externalConversationId, string $content, ?User $sender): array;

    public function isMock(): bool;
}
