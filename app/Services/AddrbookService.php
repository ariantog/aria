<?php

namespace App\Services;

use App\Models\Addrbook;

class AddrbookService
{
    /**
     * Get the Addrbook type based on the provided slug or URL segment.
     */
    public function getTypeBySlug(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        $types = collect(Addrbook::getTypes());

        return $types->firstWhere('slug', $slug);
    }
}
