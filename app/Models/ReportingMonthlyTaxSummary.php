<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingMonthlyTaxSummary extends Model
{
    protected $table = 'monthly_tax_summaries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ppn_keluaran_dpp' => 'decimal:2',
            'ppn_keluaran_tax' => 'decimal:2',
            'ppn_masukan_dpp' => 'decimal:2',
            'ppn_masukan_tax' => 'decimal:2',
            'retur_keluaran_dpp' => 'decimal:2',
            'retur_keluaran_tax' => 'decimal:2',
            'retur_masukan_dpp' => 'decimal:2',
            'retur_masukan_tax' => 'decimal:2',
            'pph_final' => 'decimal:2',
            'tax_paid' => 'decimal:2',
        ];
    }

    public function reportingEntity(): BelongsTo
    {
        return $this->belongsTo(ReportingEntity::class);
    }
}
