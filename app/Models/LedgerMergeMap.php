<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerMergeMap extends Model
{
    protected $fillable = ['old_customer_id', 'new_customer_id'];

    public function oldCustomer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'old_customer_id');
    }

    public function newCustomer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'new_customer_id');
    }
}
