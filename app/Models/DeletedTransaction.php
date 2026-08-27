<?php

namespace App\Models;

use App\Models\Concerns\DisplaysTransactionTotals;
use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Model;

class DeletedTransaction extends Model
{
    use DisplaysTransactionTotals;
    use FillsProductionColumnDefaults;
    protected $table = 'deleted';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'due' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'ppn' => 'decimal:2',
        'real_total' => 'decimal:2',
        'total_items' => 'decimal:2',
        'type' => 'integer',
        'submit_type' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(DeletedTransactionDetail::class, 'transaction_id');
    }

    public function sender()
    {
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Addrbook::class, 'receiver_id');
    }
}
