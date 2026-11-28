<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceMissedPushGap extends Model
{
    protected $table = 'marketplace_missed_push_gaps';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'platform_update_at' => 'datetime',
            'detected_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->ingested_at === null;
    }
}