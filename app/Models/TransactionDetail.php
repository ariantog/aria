<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'date' => 'date',
        'transaction_type' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('transaction', fn (Builder $q) => $q->visibleToUser($user));
    }
}
