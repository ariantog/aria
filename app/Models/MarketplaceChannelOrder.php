<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Latest known state per marketplace order (channel + order_ref).
 */
class MarketplaceChannelOrder extends Model
{
    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected $table = 'marketplace_channel_orders';

    protected $fillable = [
        'channel',
        'order_ref',
        'shop_ref',
        'order_status',
        'commerce_platform',
        'last_event_at',
        'first_received_at',
        'paid_at',
        'last_webhook_event_id',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'first_received_at' => 'datetime',
            'paid_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lastWebhookEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceWebhookEvent::class, 'last_webhook_event_id');
    }
}