<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedTransaction extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_items' => 'decimal:2',
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
