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

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(RestockSheet::class, 'restock_sheet_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
