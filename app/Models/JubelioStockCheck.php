<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JubelioStockCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_tracking',
        'sync_cursor',
        'per_type_limit',
        'demand_days',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sync_cursor' => 'integer',
            'per_type_limit' => 'integer',
            'demand_days' => 'integer',
        ];
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(JubelioStockDiscrepancy::class);
    }
}
