<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    const TYPE_BUY = 1;

    const TYPE_SELL = 2;

    const TYPE_MOVE = 3;

    const TYPE_TRANSFER = 6;

    const TYPE_CASH_OUT = 7;

    const TYPE_USE = 8;

    const TYPE_CASH_IN = 9;

    const TYPE_ADJUST = 12;

    const TYPE_RETURN = 15;

    const TYPE_PRODUCTION = 16;

    const TYPE_RETURN_SUPPLIER = 17;

    const TYPE_DEPRECIATION = 18;

    const STATUS_PENDING = 0;

    const STATUS_COMPLETED = 1;

    const STATUS_CANCELLED = 2;

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_items' => 'decimal:2',
        'type' => 'integer',
        'status' => 'integer',
        'submit_type' => 'integer',
    ];

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BUY => 'Buy',
            self::TYPE_SELL => 'Sell',
            self::TYPE_MOVE => 'Move',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_CASH_OUT => 'Cash Out',
            self::TYPE_USE => 'Use Items',
            self::TYPE_CASH_IN => 'Cash In',
            self::TYPE_ADJUST => 'Adjust',
            self::TYPE_RETURN => 'Return',
            self::TYPE_PRODUCTION => 'Production',
            self::TYPE_RETURN_SUPPLIER => 'Ret. Supplier',
            self::TYPE_DEPRECIATION => 'Depreciation',
            default => 'Unknown',
        };
    }

    public function sender()
    {
        // sender_type now stores Addrbook Type ID (int), so morphTo won't work standardly.
        // Assuming all senders are Addrbooks based on config.
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function receiver()
    {
        // receiver_type now stores Addrbook Type ID (int).
        return $this->belongsTo(Addrbook::class, 'receiver_id');
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'transactions-list',
            'create' => 'transactions-create',
            'edit' => 'transactions-edit',
            'delete' => 'transactions-delete',
            'show' => 'transactions-show',

            // Granular Types
            'type_buy' => 'transactions-type-buy',
            'type_sell' => 'transactions-type-sell',
            'type_move' => 'transactions-type-move',
            'type_cash_in' => 'transactions-type-cash-in',
            'type_cash_out' => 'transactions-type-cash-out',
            'type_transfer' => 'transactions-type-transfer',
            'type_adjust' => 'transactions-type-adjust',
            'type_return' => 'transactions-type-return',
            'type_return_supplier' => 'transactions-type-return-supplier',
        ];
    }
}
