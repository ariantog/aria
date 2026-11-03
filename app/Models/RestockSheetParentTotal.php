<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockSheetParentTotal extends Model
{
    protected $guarded = ['id'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(RestockSheet::class, 'restock_sheet_id');
    }
}
