<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated Unused — revenue entity is derived from CashIn receiver bank via reporting_entity_banks.
 */
class ReportingChannelBank extends Model
{
    protected $fillable = ['customer_id', 'bank_id', 'notes'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'bank_id');
    }
}
