<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LocationAccessService
{
    /**
     * Superadmin and users without a location see all addrbooks / transactions.
     */
    public function hasUnrestrictedLocationAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->is_superadmin || $user->location_id === null;
    }

    public function applyAddrbookScope(Builder $query, ?User $user): Builder
    {
        if ($this->hasUnrestrictedLocationAccess($user)) {
            return $query;
        }

        $locationId = $user->location_id;

        return $query->whereHas('locations', fn (Builder $q) => $q->where('locations.id', $locationId));
    }

    public function applyTransactionScope(Builder $query, ?User $user): Builder
    {
        if ($this->hasUnrestrictedLocationAccess($user)) {
            return $query;
        }

        $locationId = $user->location_id;

        return $query->where(function (Builder $q) use ($locationId) {
            $q->whereHas('sender.locations', fn (Builder $lq) => $lq->where('locations.id', $locationId))
                ->orWhereHas('receiver.locations', fn (Builder $lq) => $lq->where('locations.id', $locationId));
        });
    }

    public function canAccessAddrbook(?User $user, Addrbook $addrbook): bool
    {
        if ($this->hasUnrestrictedLocationAccess($user)) {
            return true;
        }

        return $addrbook->locations()->where('locations.id', $user->location_id)->exists();
    }
}
