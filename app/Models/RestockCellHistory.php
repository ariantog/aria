<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockCellHistory extends Model
{
    protected $guarded = ['id'];

    public function cell(): BelongsTo
    {
        return $this->belongsTo(RestockCell::class, 'restock_cell_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
