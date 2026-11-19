<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrderShadow extends Model
{
    public const CHANNEL_SHOPEE = 'shopee';

    public const COMPARE_PENDING = 'pending';

    public const COMPARE_MATCHED = 'matched';

    public const COMPARE_MISMATCH = 'mismatch';

    public const COMPARE_JUBELIO_MISSING = 'jubelio_missing';

    protected $fillable = [
        'channel',
        'shop_id',
        'external_order_sn',
        'channel_status',
        'last_push_code',
        'compare_status',
        'jubelio_invoice',
        'jubelio_order_status',
        'jubelio_order_id',
        'channel_payload',
        'compare_note',
        'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'last_push_code' => 'integer',
            'jubelio_order_id' => 'integer',
            'channel_payload' => 'array',
            'last_event_at' => 'datetime',
        ];
    }
}