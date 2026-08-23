<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JubelioStockDiscrepancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'jubelio_stock_check_id',
        'item_id',
        'jubelio_item_id',
        'jubelio_location_id',
        'jubelio_location_name',
        'warehouse_id',
        'aria_qty',
        'jubelio_qty',
        'jubelio_on_hand',
        'jubelio_on_order',
        'jubelio_available',
        'jubelio_reserved',
    ];

    public function getQtyDiffAttribute(): float
    {
        return (float) $this->aria_qty - (float) $this->jubelio_qty;
    }

    public function getAbsQtyDiffAttribute(): float
    {
        return abs($this->qty_diff);
    }

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(JubelioStockCheck::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }
}
