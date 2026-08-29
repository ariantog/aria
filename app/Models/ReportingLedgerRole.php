<?php

namespace App\Models;

use App\Enums\ReportingLedgerRole as ReportingLedgerRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingLedgerRole extends Model
{
    protected $fillable = ['customer_id', 'role'];

    protected function casts(): array
    {
        return ['role' => ReportingLedgerRoleEnum::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }

    /**
     * @return list<int>
     */
    public static function customerIdsFor(ReportingLedgerRoleEnum $role): array
    {
        return static::query()
            ->where('role', $role->value)
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
