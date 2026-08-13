<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LocationAccessService
{
    /**
     * Superadmin and users without a location see all customers / transactions.
     * Legacy rows may store an unset location as 0 instead of NULL.
     */
    public function hasUnrestrictedLocationAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (User::isSuperadmin($user)) {
            return true;
        }

        return (int) $user->location_id <= 0;
    }

    public function applyAddrbookScope(Builder $query, ?User $user): Builder
    {
        if ($this->hasUnrestrictedLocationAccess($user)) {
            return $query;
        }

        $locationId = $user->location_id;

        return $query->whereHas('locations', fn (Builder $q) => $q->where('locations.id', $locationId));
    }

    /**
     * User sees a transaction when the sender OR the receiver is in their location.
     */
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

    public function canAccessTransaction(?User $user, Transaction $transaction): bool
    {
        if ($this->hasUnrestrictedLocationAccess($user)) {
            return true;
        }

        $transaction->loadMissing(['sender', 'receiver']);

        if ($transaction->sender && $this->canAccessAddrbook($user, $transaction->sender)) {
            return true;
        }

        if ($transaction->receiver && $this->canAccessAddrbook($user, $transaction->receiver)) {
            return true;
        }

        return false;
    }

    /**
     * Jubelio sell/return transactions use a synced warehouse + customer pair. Users only
     * see transactions when the sender OR receiver is linked to their location, but
     * warehouses could not be assigned to locations until this helper runs.
     */
    public function ensureJubelioPartyLocations(Addrbook $warehouse, Addrbook $customer): void
    {
        $locationIds = $this->resolveJubelioPartyLocationIds($warehouse, $customer);

        if ($locationIds->isEmpty()) {
            return;
        }

        $ids = $locationIds->all();

        $warehouse->locations()->syncWithoutDetaching($ids);
        $customer->locations()->syncWithoutDetaching($ids);
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveJubelioPartyLocationIds(Addrbook $warehouse, Addrbook $customer): Collection
    {
        $locationIds = $warehouse->locations()->pluck('locations.id')
            ->merge($customer->locations()->pluck('locations.id'))
            ->unique()
            ->values();

        if ($locationIds->isNotEmpty()) {
            return $locationIds;
        }

        return Location::query()->orderBy('id')->pluck('id');
    }
}
