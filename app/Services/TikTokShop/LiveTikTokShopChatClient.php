<?php

namespace App\Services\TikTokShop;

use App\Contracts\TikTokShop\TikTokShopChatClient;
use App\Models\User;
use RuntimeException;

/**
 * Placeholder for TikTok Shop Open Platform customer-service APIs once partner credentials are approved.
 */
class LiveTikTokShopChatClient implements TikTokShopChatClient
{
    public function isMock(): bool
    {
        return false;
    }

    public function listConversations(int $pageSize = 20, ?string $pageToken = null): array
    {
        $this->guardConfigured();

        throw new RuntimeException('Live TikTok Shop chat belum diimplementasi — set TIKTOK_SHOP_DRIVER=mock sementara partner review.');
    }

    public function getConversation(string $externalConversationId): ?array
    {
        $this->guardConfigured();

        throw new RuntimeException('Live TikTok Shop chat belum diimplementasi.');
    }

    public function listMessages(string $externalConversationId, int $pageSize = 50, ?string $pageToken = null): array
    {
        $this->guardConfigured();

        throw new RuntimeException('Live TikTok Shop chat belum diimplementasi.');
    }

    public function sendSellerMessage(string $externalConversationId, string $content, ?User $sender): array
    {
        $this->guardConfigured();

        throw new RuntimeException('Live TikTok Shop chat belum diimplementasi.');
    }

    private function guardConfigured(): void
    {
        if (! config('tiktok_shop.app_key') || ! config('tiktok_shop.app_secret')) {
            throw new RuntimeException('TikTok Shop API credentials belum di-set (TIKTOK_SHOP_APP_KEY / TIKTOK_SHOP_APP_SECRET).');
        }
    }
}
