<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps one Aria warehouse (addrbook) to one Jubelio location / store / bin.
 *
 * Outbound stock push (AdjustStock) looks up this row by warehouse_id and
 * sends jubelio_location_id (+ bin_id when > 0) to Jubelio.
 *
 * @see \App\Services\Jubelio\JubelioStockSync
 */
class Jubeliosync extends Model
{
    /** @use HasFactory<\Database\Factories\JubeliosyncFactory> */
    use HasFactory;

    protected $guarded = [];

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'customer_id');
    }
}
