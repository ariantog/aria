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
        'jubelio_item_id',
        'jubelio_location_id',
        'warehouse_id',
        'aria_qty',
        'jubelio_qty',
    ];

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(JubelioStockCheck::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }
}
