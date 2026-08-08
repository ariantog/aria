<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseArrangementCandidateSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_stock' => 'integer',
            'suggested_qty' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(WarehouseArrangementCandidate::class, 'candidate_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'source_warehouse_id');
    }
}
