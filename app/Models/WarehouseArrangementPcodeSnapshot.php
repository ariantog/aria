<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseArrangementPcodeSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'completeness_pct' => 'float',
            'family_demand_365' => 'float',
            'sizes' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'destination_warehouse_id');
    }
}
