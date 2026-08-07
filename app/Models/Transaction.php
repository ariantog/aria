<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @deprecated Use TransactionType enum */
    const TYPE_BUY = 1;

    /** @deprecated Use TransactionType enum */
    const TYPE_SELL = 2;

    /** @deprecated Use TransactionType enum */
    const TYPE_MOVE = 3;

    /** @deprecated Use TransactionType enum */
    const TYPE_TRANSFER = 6;

    /** @deprecated Use TransactionType enum */
    const TYPE_CASH_OUT = 7;

    /** @deprecated Use TransactionType enum */
    const TYPE_USE = 8;

    /** @deprecated Use TransactionType enum */
    const TYPE_CASH_IN = 9;

    /** @deprecated Use TransactionType enum */
    const TYPE_ADJUST = 12;

    /** @deprecated Use TransactionType enum */
    const TYPE_RETURN = 15;

    /** @deprecated Use TransactionType enum */
    const TYPE_PRODUCTION = 16;

    /** @deprecated Use TransactionType enum */
    const TYPE_RETURN_SUPPLIER = 17;

    /** @deprecated Use TransactionType enum */
    const TYPE_DEPRECIATION = 18;

    /** @deprecated Use TransactionStatus enum */
    const STATUS_PENDING = 0;

    /** @deprecated Use TransactionStatus enum */
    const STATUS_COMPLETED = 1;

    /** @deprecated Use TransactionStatus enum */
    const STATUS_CANCELLED = 2;

    const SUBMIT_TYPE_MANUAL = 1;

    const SUBMIT_TYPE_JUBELIO = 2;

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date',
            'total' => 'decimal:2', 'discount' => 'decimal:2',
            'discount_percent' => 'decimal:2', 'adjustment' => 'decimal:2',
            'grand_total' => 'decimal:2', 'total_items' => 'decimal:2',
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'submit_type' => 'integer',
        ];
    }

    public static function getTypes(): array
    {
        return array_map(fn (TransactionType $type) => ['id' => $type->value, 'name' => $type->label()], TransactionType::cases());
    }

    public function getTypeLabel(): string
    {
        return $this->type instanceof TransactionType ? $this->type->label() : 'Unknown';
    }

    public function getSubmitTypeLabel(): string
    {
        return match ($this->submit_type) {
            self::SUBMIT_TYPE_MANUAL => 'Manual', self::SUBMIT_TYPE_JUBELIO => 'Jubelio Sync', default => 'Unknown'
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
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function receiver()
    {
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

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        return app(\App\Services\LocationAccessService::class)->applyTransactionScope($query, $user);
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'transactions-list', 'create' => 'transactions-create',
            'edit' => 'transactions-edit', 'delete' => 'transactions-delete', 'show' => 'transactions-show',
            'type-buy' => 'transactions-type-buy', 'type-sell' => 'transactions-type-sell',
            'type-move' => 'transactions-type-move', 'type-cash-in' => 'transactions-type-cash-in',
            'type-cash-out' => 'transactions-type-cash-out', 'type-transfer' => 'transactions-type-transfer',
            'type-adjust' => 'transactions-type-adjust', 'type-return' => 'transactions-type-return',
            'type-return-supplier' => 'transactions-type-return-supplier',
            'transaction-sync' => 'transactions-transaction-sync',
        ];
    }
}
