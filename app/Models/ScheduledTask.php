<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTask extends Model
{
    protected $fillable = [
        'name',
        'command',
        'frequency',
        'is_active',
        'description',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
