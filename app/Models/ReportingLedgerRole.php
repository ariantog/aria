<?php

namespace App\Models;

use App\Enums\ReportingLedgerRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingLedgerRole extends Model
{
    protected $fillable = ['customer_id', 'role'];

    protected function casts(): array
    {
        return ['role' => ReportingLedgerRole::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
