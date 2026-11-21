<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inbound TikTok Shop webhook payloads (receive-only — no order posting or stock).
 */
class TikTokShopWebhookInbox extends Model
{
    public $timestamps = false;

    protected $table = 'tiktok_shop_webhook_inbox';

    protected $fillable = [
        'tts_notification_id',
        'event_type',
        'shop_id',
        'order_id',
        'order_status',
        'signature_ok',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => 'integer',
            'signature_ok' => 'boolean',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}