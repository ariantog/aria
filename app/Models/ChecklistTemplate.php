<?php

namespace App\Models;

use App\Enums\ChecklistFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public static function getPermissions(): array
    {
        return [
            'edit' => 'checklist-templates-edit',
            'delete' => 'checklist-templates-delete',
        ];
    }

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
