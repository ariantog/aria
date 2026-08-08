<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseArrangementCandidate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'demand_30' => 'float',
            'demand_90' => 'float',
            'demand_180' => 'float',
            'demand_365' => 'float',
            'synced_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'destination_warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(WarehouseArrangementCandidateSource::class, 'candidate_id');
    }

    public function demandForDays(int $days): float
    {
        return match ($days) {
            30 => (float) $this->demand_30,
            90 => (float) $this->demand_90,
            180 => (float) $this->demand_180,
            default => (float) $this->demand_365,
        };
    }
}
