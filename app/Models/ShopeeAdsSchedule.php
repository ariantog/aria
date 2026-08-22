<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAdsSchedule extends Model
{
    protected $fillable = [
        'ad_type',
        'run_time',
        'increment_idr',
        'enabled',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
