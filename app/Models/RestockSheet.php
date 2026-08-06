<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestockSheet extends Model
{
    protected $guarded = ['id'];

    public static function getPermissions(): array
    {
        return [
            'view' => 'restock-list',
            'create' => 'restock-create',
            'edit' => 'restock-edit',
            'delete' => 'restock-delete',
            'history' => 'restock-history',
        ];
    }

    protected function casts(): array
    {
        return [
            'last_saved_at' => 'datetime',
        ];
    }

    public function typeTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'type_tag_id');
    }

    public function representativeGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class, 'representative_group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastSavedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_saved_by');
    }

    public function cells(): HasMany
    {
        return $this->hasMany(RestockCell::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->representativeGroup?->image_url ?? asset('images/default-item.svg');
    }
}
