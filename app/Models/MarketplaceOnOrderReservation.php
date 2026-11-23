<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for marketplace on_order increments (per order line).
 */
class MarketplaceOnOrderReservation extends Model
{
    protected $table = 'marketplace_on_order_reservations';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
    ];
}