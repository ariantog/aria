<?php

namespace App\Services\TikTokShop;

class TikTokShopChatPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function conversation(array $row): array
    {
        $lastAt = $row['last_message_at'] ?? null;

        return [
            'conversation_id' => $row['conversation_id'],
            'buyer_nickname' => $row['buyer_nickname'],
            'buyer_avatar_url' => $row['buyer_avatar_url'] ?? null,
            'order_id' => $row['order_id'] ?? null,
            'unread_count' => (int) ($row['unread_count'] ?? 0),
            'latest_message' => [
                'content' => $row['last_message_text'] ?? null,
                'create_time' => is_int($lastAt) ? $lastAt : null,
            ],
            'update_time' => is_int($lastAt) ? $lastAt : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function message(array $row): array
    {
        return [
            'message_id' => $row['message_id'],
            'conversation_id' => $row['conversation_id'] ?? null,
            'sender_role' => $row['sender_role'],
            'content' => $row['content'],
            'create_time' => $row['create_time'] ?? null,
            'aria_user_id' => $row['aria_user_id'] ?? null,
        ];
    }
}
