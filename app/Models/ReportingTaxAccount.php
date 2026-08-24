<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingTaxAccount extends Model
{
    protected $fillable = ['legacy_ledger_id', 'reporting_entity_id', 'tax_type'];

    public function legacyLedger(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'legacy_ledger_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(ReportingEntity::class, 'reporting_entity_id');
    }

    public static function findForLedger(int $ledgerId): ?self
    {
        $canonicalId = LedgerMergeMap::resolveCanonicalCustomerId($ledgerId);

        return static::query()->where('legacy_ledger_id', $canonicalId)->first();
    }
}
