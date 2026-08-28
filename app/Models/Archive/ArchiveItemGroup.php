<?php

namespace App\Models\Archive;

use App\Models\ItemGroup;

class ArchiveItemGroup extends ItemGroup
{
    use UsesArchiveConnection;

    protected $table = 'item_group';

    protected static function booted(): void
    {
        // Read-only archive rows.
    }
}
