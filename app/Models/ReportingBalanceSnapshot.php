<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingBalanceSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'balance' => 'decimal:2',
            'customer_type' => 'integer',
        ];
    }

    public function addrbook(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }

    public function reportingEntity(): BelongsTo
    {
        return $this->belongsTo(ReportingEntity::class);
    }
}
