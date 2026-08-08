<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPerformanceRollup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'net_qty' => 'decimal:2',
            'net_value' => 'decimal:2',
            'pct_of_total' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }
}
