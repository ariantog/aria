<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffRole extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class)->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_role_user', 'staff_role_id', 'user_id')
            ->withTimestamps();
    }
}
