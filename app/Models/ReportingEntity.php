<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingEntity extends Model
{
    protected $fillable = [
        'name', 'slug', 'is_pkp', 'npwp', 'modal', 'laba_ditahan_awal', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_pkp' => 'boolean',
            'is_active' => 'boolean',
            'modal' => 'decimal:2',
            'laba_ditahan_awal' => 'decimal:2',
        ];
    }

    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(Addrbook::class, 'reporting_entity_banks', 'reporting_entity_id', 'bank_id')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function taxAccounts(): HasMany
    {
        return $this->hasMany(ReportingTaxAccount::class);
    }

    /**
     * Resolve the active reporting entity for a bank (CashIn receiver).
     */
    public static function findActiveForBank(int $bankId): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->whereHas('banks', fn ($query) => $query->where('customers.id', $bankId))
            ->first();
    }

    /**
     * @return list<int>
     */
    public static function activePkpBankIds(): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_pkp', true)
            ->whereHas('banks')
            ->with(['banks' => fn ($query) => $query->select('customers.id')])
            ->get()
            ->flatMap(fn (self $entity) => $entity->banks->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
