<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

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

    const SUBMIT_TYPE_MANUAL = 1;

    const SUBMIT_TYPE_JUBELIO = 2;

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_items' => 'decimal:2',
        'type' => 'integer',
        'status' => 'integer',
        'submit_type' => 'integer',
    ];

    public static function getTypes(): array
    {
        return [
            ['id' => self::TYPE_BUY, 'name' => 'Buy'],
            ['id' => self::TYPE_SELL, 'name' => 'Sell'],
            ['id' => self::TYPE_MOVE, 'name' => 'Move'],
            ['id' => self::TYPE_TRANSFER, 'name' => 'Transfer'],
            ['id' => self::TYPE_CASH_OUT, 'name' => 'Cash Out'],
            ['id' => self::TYPE_USE, 'name' => 'Use Items'],
            ['id' => self::TYPE_CASH_IN, 'name' => 'Cash In'],
            ['id' => self::TYPE_ADJUST, 'name' => 'Adjust'],
            ['id' => self::TYPE_RETURN, 'name' => 'Return'],
            ['id' => self::TYPE_PRODUCTION, 'name' => 'Production'],
            ['id' => self::TYPE_RETURN_SUPPLIER, 'name' => 'Ret. Supplier'],
            ['id' => self::TYPE_DEPRECIATION, 'name' => 'Depreciation'],
        ];
    }

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

    public function getSubmitTypeLabel(): string
    {
        return match ($this->submit_type) {
            self::SUBMIT_TYPE_MANUAL => 'Manual',
            self::SUBMIT_TYPE_JUBELIO => 'Jubelio Sync',
            default => 'Unknown',
        };
    }

    public function isManual(): bool
    {
        return $this->submit_type === self::SUBMIT_TYPE_MANUAL;
    }

    public function isFromJubelio(): bool
    {
        return $this->submit_type === self::SUBMIT_TYPE_JUBELIO;
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

    public function submitByA()
    {
        return $this->belongsTo(User::class, 'a_submit_by');
    }

    public function submitByB()
    {
        return $this->belongsTo(User::class, 'b_submit_by');
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
            'type-buy' => 'transactions-type-buy',
            'type-sell' => 'transactions-type-sell',
            'type-move' => 'transactions-type-move',
            'type-cash-in' => 'transactions-type-cash-in',
            'type-cash-out' => 'transactions-type-cash-out',
            'type-transfer' => 'transactions-type-transfer',
            'type-adjust' => 'transactions-type-adjust',
            'type-return' => 'transactions-type-return',
            'type-return-supplier' => 'transactions-type-return-supplier',
            'transaction-sync' => 'transactions-transaction-sync',
        ];
    }
}
