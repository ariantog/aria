<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingWarehouseFulfillment extends Model
{
    protected $table = 'reporting_warehouse_fulfillment';

    protected $fillable = ['warehouse_id', 'customer_id', 'notes'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
