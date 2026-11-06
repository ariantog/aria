<?php

namespace App\Services\TikTokShop;

use App\Models\TikTokShopConversation;
use App\Models\TikTokShopMessage;
use Illuminate\Support\Carbon;

class TikTokShopMockChatSeeder
{
    public function seedIfEmpty(): void
    {
        if (TikTokShopConversation::query()->exists()) {
            return;
        }

        $this->seedDemo();
    }

    public function resetAndSeed(): void
    {
        TikTokShopMessage::query()->delete();
        TikTokShopConversation::query()->delete();

        $this->seedDemo();
    }

    private function seedDemo(): void
    {
        $now = Carbon::now();

        $conv1 = $this->createConversation(
            'conv_7123456789012345001',
            'Sari Wijaya',
            '5790123456789012345',
            $now->copy()->subHours(2),
        );

        $this->addMessage($conv1, 'msg_7001_a', TikTokShopMessage::SENDER_BUYER, 'Kak, ukuran M ready nggak untuk RUNNING SHIRT hitam?', $now->copy()->subHours(2));
        $this->addMessage($conv1, 'msg_7001_b', TikTokShopMessage::SENDER_SELLER, 'Ready kak, stok Pusat masih ada. Mau di-reserve?', $now->copy()->subHours(2)->addMinutes(8));
        $this->addMessage($conv1, 'msg_7001_c', TikTokShopMessage::SENDER_BUYER, 'Iya reserve ya, nanti saya checkout malam ini.', $now->copy()->subMinutes(45));

        $conv2 = $this->createConversation(
            'conv_7123456789012345002',
            'Budi Santoso',
            '5790123456789012346',
            $now->copy()->subHours(5),
        );

        $this->addMessage($conv2, 'msg_7002_a', TikTokShopMessage::SENDER_BUYER, 'Pesanan saya sudah dikirim belum? Invoice 5790123456789012346', $now->copy()->subHours(5));
        $this->addMessage($conv2, 'msg_7002_b', TikTokShopMessage::SENDER_SYSTEM, 'Buyer opened chat from order detail.', $now->copy()->subHours(5)->addMinute());

        $conv3 = $this->createConversation(
            'conv_7123456789012345003',
            'Dewi Lestari',
            null,
            $now->copy()->subDay(),
        );

        $this->addMessage($conv3, 'msg_7003_a', TikTokShopMessage::SENDER_BUYER, 'Bisa tukar size L ke XL?', $now->copy()->subDay());
    }

    private function createConversation(
        string $externalId,
        string $nickname,
        ?string $orderId,
        Carbon $lastAt,
    ): TikTokShopConversation {
        return TikTokShopConversation::query()->create([
            'external_conversation_id' => $externalId,
            'buyer_nickname' => $nickname,
            'buyer_avatar_url' => null,
            'order_id' => $orderId,
            'unread_count' => 0,
            'last_message_text' => null,
            'last_message_at' => $lastAt,
        ]);
    }

    private function addMessage(
        TikTokShopConversation $conversation,
        string $externalMessageId,
        string $senderRole,
        string $content,
        Carbon $sentAt,
    ): TikTokShopMessage {
        $message = TikTokShopMessage::query()->create([
            'tiktok_shop_conversation_id' => $conversation->id,
            'external_message_id' => $externalMessageId,
            'sender_role' => $senderRole,
            'content' => $content,
            'aria_user_id' => null,
            'sent_at' => $sentAt,
        ]);

        $conversation->forceFill([
            'last_message_text' => mb_substr($content, 0, 500),
            'last_message_at' => $sentAt,
            'unread_count' => $senderRole === TikTokShopMessage::SENDER_BUYER
                ? (int) $conversation->unread_count + 1
                : $conversation->unread_count,
        ])->save();

        return $message;
    }
}
