<?php

namespace App\Models;

use App\Enums\ChecklistFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'frequency' => ChecklistFrequency::class,
        ];
    }

    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChecklistCompletion::class);
    }
}
