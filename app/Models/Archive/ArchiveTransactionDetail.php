<?php

namespace App\Models\Archive;

use App\Models\TransactionDetail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveTransactionDetail extends TransactionDetail
{
    use UsesArchiveConnection;

    protected $table = 'transaction_details';

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ArchiveTransaction::class, 'transaction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class, 'item_id');
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
