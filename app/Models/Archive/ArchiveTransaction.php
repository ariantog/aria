<?php

namespace App\Models\Archive;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveTransaction extends Transaction
{
    use UsesArchiveConnection;

    protected $table = 'transactions';

    protected static function booted(): void
    {
        // Read-only archive rows — skip live Transaction observer side effects.
    }

    public function details(): HasMany
    {
        return $this->hasMany(ArchiveTransactionDetail::class, 'transaction_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(ArchiveAddrbook::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(ArchiveAddrbook::class, 'receiver_id');
    }
}
