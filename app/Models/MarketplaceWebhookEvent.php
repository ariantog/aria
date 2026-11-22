<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Shopee/TikTok webhook history (deduped by idempotency_key).
 */
class MarketplaceWebhookEvent extends Model
{
    public $timestamps = false;

    protected $table = 'marketplace_webhook_events';

    protected $fillable = [
        'channel',
        'idempotency_key',
        'event_type',
        'shop_ref',
        'order_ref',
        'order_status',
        'commerce_platform',
        'signature_ok',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_ok' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

}