<?php

namespace App\Services\TikTokShop;

use App\Models\TikTokShopConversation;
use App\Models\TikTokShopMessage;
use Carbon\CarbonInterface;

class TikTokShopChatPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function conversation(TikTokShopConversation $row): array
    {
        return [
            'conversation_id' => $row->external_conversation_id,
            'buyer_nickname' => $row->buyer_nickname,
            'buyer_avatar_url' => $row->buyer_avatar_url,
            'order_id' => $row->order_id,
            'unread_count' => (int) $row->unread_count,
            'latest_message' => [
                'content' => $row->last_message_text,
                'create_time' => $this->epoch($row->last_message_at),
            ],
            'update_time' => $this->epoch($row->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function message(TikTokShopMessage $row): array
    {
        return [
            'message_id' => $row->external_message_id,
            'conversation_id' => $row->conversation?->external_conversation_id,
            'sender_role' => $row->sender_role,
            'content' => $row->content,
            'create_time' => $this->epoch($row->sent_at),
            'aria_user_id' => $row->aria_user_id,
        ];
    }

    public function epoch(?CarbonInterface $at): ?int
    {
        return $at?->getTimestamp();
    }
}
