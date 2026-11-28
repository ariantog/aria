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
        'marketplace_ordered_at',
        'last_webhook_event_id',
        'updated_at',
        'awb_printed_by',
        'awb_printed_at',
        'awb_document_requested_at',
        'awb_document_ready_at',
        'posted_transaction_id',
        'posted_at',
        'posted_return_transaction_id',
        'posted_return_at',
        'return_verified_at',
        'return_verified_by',
        'return_verify_note',
        'return_dismissed_at',
        'return_dismissed_by',
        'return_dismiss_note',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'first_received_at' => 'datetime',
            'paid_at' => 'datetime',
            'marketplace_ordered_at' => 'datetime',
            'updated_at' => 'datetime',
            'aria_warehouse_id' => 'integer',
            'awb_printed_by' => 'integer',
            'awb_printed_at' => 'datetime',
            'awb_document_requested_at' => 'datetime',
            'awb_document_ready_at' => 'datetime',
            'posted_transaction_id' => 'integer',
            'posted_at' => 'datetime',
            'posted_return_transaction_id' => 'integer',
            'posted_return_at' => 'datetime',
            'return_verified_at' => 'datetime',
            'return_verified_by' => 'integer',
            'return_dismissed_at' => 'datetime',
            'return_dismissed_by' => 'integer',
        ];
    }

    public function returnDismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_dismissed_by');
    }

    public function returnVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_verified_by');
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