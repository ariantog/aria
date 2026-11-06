<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TikTokShopConversation extends Model
{
    protected $table = 'tiktok_shop_conversations';

    protected $fillable = [
        'external_conversation_id',
        'buyer_nickname',
        'buyer_avatar_url',
        'order_id',
        'unread_count',
        'last_message_text',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TikTokShopMessage::class, 'tiktok_shop_conversation_id');
    }
}
