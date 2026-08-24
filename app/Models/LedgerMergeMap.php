<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerMergeMap extends Model
{
    protected $fillable = ['old_customer_id', 'new_customer_id'];

    /**
     * Follow merge_map chains so historical transactions on retired ledgers roll up to the canonical account.
     */
    public static function resolveCanonicalCustomerId(int $customerId): int
    {
        $current = $customerId;
        $guard = 0;

        while ($guard++ < 20) {
            $mapped = static::query()
                ->where('old_customer_id', $current)
                ->value('new_customer_id');

            if (! $mapped) {
                return $current;
            }

            $current = (int) $mapped;
        }

        return $current;
    }

    public function oldCustomer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'old_customer_id');
    }

    public function newCustomer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'new_customer_id');
    }
}
