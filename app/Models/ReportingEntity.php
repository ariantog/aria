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
}
