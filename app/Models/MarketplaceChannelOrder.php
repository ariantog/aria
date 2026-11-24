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
        'platform_warehouse_id',
        'platform_location_id',
        'aria_warehouse_id',
        'last_event_at',
        'first_received_at',
        'paid_at',
        'last_webhook_event_id',
        'updated_at',
        'awb_printed_by',
        'awb_printed_at',
        'awb_document_requested_at',
        'awb_document_ready_at',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'first_received_at' => 'datetime',
            'paid_at' => 'datetime',
            'updated_at' => 'datetime',
            'aria_warehouse_id' => 'integer',
            'awb_printed_by' => 'integer',
            'awb_printed_at' => 'datetime',
            'awb_document_requested_at' => 'datetime',
            'awb_document_ready_at' => 'datetime',
        ];
    }

    public function awbPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awb_printed_by');
    }

    public function lastWebhookEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceWebhookEvent::class, 'last_webhook_event_id');
    }
}