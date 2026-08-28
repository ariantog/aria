<?php

namespace App\Models\Archive;

trait UsesArchiveConnection
{
    public function getConnectionName(): ?string
    {
        return 'archive';
    }
}
