<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps one Aria warehouse (addrbook) to one Jubelio location / store / bin.
 *
 * Outbound stock push (AdjustStock) looks up this row by warehouse_id and
 * sends jubelio_location_id (+ bin_id when > 0) to Jubelio.
 *
 * @see \App\Services\Jubelio\JubelioStockSync
 */
class Jubeliosync extends Model
{
    /** @use HasFactory<\Database\Factories\JubeliosyncFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Jubelio uses negative location ids (e.g. -1 = "Pusat"). Only 0 means unset in Aria.
     */
    public static function isMappedStoreId(int $storeId): bool
    {
        return $storeId > 0;
    }

    public static function isMappedLocationId(int $locationId): bool
    {
        return $locationId !== 0;
    }

    public static function hasMappedStoreLocationPair(int $storeId, int $locationId): bool
    {
        return self::isMappedStoreId($storeId) && self::isMappedLocationId($locationId);
    }

    /**
     * @param  Builder<Jubeliosync>  $query
     * @return Builder<Jubeliosync>
     */
    public function scopeWhereMappedJubelioLocation(Builder $query): Builder
    {
        return $query->where('jubelio_location_id', '!=', 0);
    }

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'customer_id');
    }
}
