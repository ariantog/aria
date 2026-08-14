<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTask extends Model
{
    protected $fillable = [
        'name',
        'command',
        'frequency',
        'active',
        'description',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'setting-cron-manager-view',
            'edit' => 'setting-cron-manager-edit',
        ];
    }
}
