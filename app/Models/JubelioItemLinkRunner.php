<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JubelioItemLinkRunner extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paused' => 'boolean',
            'calls_hour_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public static function state(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['paused' => false],
        );
    }
}
