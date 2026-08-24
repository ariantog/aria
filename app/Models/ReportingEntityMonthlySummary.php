<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingEntityMonthlySummary extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cash_in' => 'decimal:2',
        ];
    }

    public function reportingEntity(): BelongsTo
    {
        return $this->belongsTo(ReportingEntity::class);
    }
}
