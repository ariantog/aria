<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokShopMessage extends Model
{
    protected $table = 'tiktok_shop_messages';

    public const SENDER_BUYER = 'BUYER';

    public const SENDER_SELLER = 'SELLER';

    public const SENDER_SYSTEM = 'SYSTEM';

    protected $fillable = [
        'tiktok_shop_conversation_id',
        'external_message_id',
        'sender_role',
        'content',
        'aria_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'aria_user_id' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TikTokShopConversation::class, 'tiktok_shop_conversation_id');
    }
}
