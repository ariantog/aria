<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory, FillsProductionColumnDefaults;

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

    public function sender()
    {
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Addrbook::class, 'receiver_id');
    }

    public static function typeLabel(int $type): string
    {
        return match ($type) {
            Transaction::TYPE_BUY => 'Buy',
            Transaction::TYPE_SELL => 'Sell',
            Transaction::TYPE_MOVE => 'Move',
            Transaction::TYPE_TRANSFER => 'Transfer',
            Transaction::TYPE_CASH_OUT => 'Cash Out',
            Transaction::TYPE_USE => 'Use',
            Transaction::TYPE_CASH_IN => 'Cash In',
            Transaction::TYPE_ADJUST => 'Adjust',
            Transaction::TYPE_RETURN => 'Return',
            Transaction::TYPE_PRODUCTION => 'Production',
            Transaction::TYPE_RETURN_SUPPLIER => 'Ret. Supplier',
            Transaction::TYPE_DEPRECIATION => 'Depreciation',
            default => 'Unknown',
        };
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('transaction', fn (Builder $q) => $q->visibleToUser($user));
    }
}
