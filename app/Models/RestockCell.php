<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestockCell extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'urgent_manual' => 'boolean',
            'urgent_flagged_at' => 'datetime',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(RestockSheet::class, 'restock_sheet_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'color_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'size_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RestockCellHistory::class);
    }
}
