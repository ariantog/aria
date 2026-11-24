<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of omnichannel AWB print actions (channel + order_ref).
 */
class MarketplaceChannelOrderAwbPrint extends Model
{
    public $timestamps = false;

    protected $table = 'marketplace_channel_order_awb_prints';

    protected $fillable = [
        'channel',
        'order_ref',
        'user_id',
        'printed_at',
        'is_reprint',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'printed_at' => 'datetime',
            'is_reprint' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}