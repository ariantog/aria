<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inbound Shopee Push Mechanism payloads (test / observability only — no order posting).
 */
class ShopeePushInbox extends Model
{
    public $timestamps = false;

    protected $table = 'shopee_push_inbox';

    protected $fillable = [
        'shop_id',
        'push_code',
        'ordersn',
        'order_status',
        'signature_ok',
        'callback_url',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'push_code' => 'integer',
            'signature_ok' => 'boolean',
            'payload' => 'array',
        ];
    }
}