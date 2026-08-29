<?php

namespace App\Models\Archive;

use App\Models\Item;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveItem extends Item
{
    use UsesArchiveConnection;

    protected $table = 'items';

    protected static function booted(): void
    {
        // Read-only archive rows.
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ArchiveItemGroup::class, 'group_id');
    }
}
