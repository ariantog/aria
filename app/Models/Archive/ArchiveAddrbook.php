<?php

namespace App\Models\Archive;

use App\Models\Addrbook;

class ArchiveAddrbook extends Addrbook
{
    use UsesArchiveConnection;

    protected $table = 'customers';

    protected static function booted(): void
    {
        // Read-only archive rows.
    }
}
