<?php

namespace App\Models;

use App\Models\Concerns\DisplaysTransactionTotals;
use App\Support\ProductionColumnDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Sales / inventory / cash document.
 *
 * Jubelio stock-sync columns keep L10 names. Do not rename them.
 *
 * a_* is the SENDER warehouse (deduct in Jubelio). b_* is the RECEIVER
 * warehouse (add in Jubelio). "A/B" is not account / debit-credit.
 *
 * @property int|null $a_submit_by User who successfully pushed sender-warehouse stock to Jubelio.
 * @property int|null $b_submit_by User who successfully pushed receiver-warehouse stock to Jubelio.
 * @property string|null $a_reference_id Jubelio item_adj_id for the sender-warehouse adjustment.
 * @property string|null $b_reference_id Jubelio item_adj_id for the receiver-warehouse adjustment.
 * @property int $submit_a_count Sender-side push attempts. Warning when > 0 and a_submit_by is null.
 * @property int $submit_b_count Receiver-side push attempts. Warning when > 0 and b_submit_by is null.
 * @property int $submit_type 1 = Aria manual (may push to Jubelio). 2 = created from Jubelio (do not push).
 *
 * @see \App\Services\Jubelio\JubelioStockSync
 */
class Transaction extends Model
{
    use DisplaysTransactionTotals;
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

    const SUBMIT_TYPE_MANUAL = 1; // L10: aria submit

    const SUBMIT_TYPE_JUBELIO = 2; // L10: cron jubelio

    /** L10 Jubelio cron used -100 when no human submitter (production user_id is NOT NULL). */
    const JUBELIO_CRON_USER_ID = -100;

    public static function resolveJubelioCronUserId(): ?int
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return self::JUBELIO_CRON_USER_ID;
        }

        $userId = User::query()->orderBy('id')->value('id');

        return $userId !== null ? (int) $userId : null;
    }

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due' => 'date',
            'total' => 'decimal:2', 'discount' => 'decimal:2',
            'adjustment' => 'decimal:2', 'ppn' => 'decimal:2', 'ppn_dpp' => 'decimal:2', 'pph' => 'decimal:2',
            'real_total' => 'decimal:2', 'total_items' => 'decimal:2',
            'type' => 'integer',
            'status' => 'integer',
            'submit_type' => 'integer',
        ];
    }

    public static function typeLabel(int $type): string
    {
        return match ($type) {
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

    public static function typePriceSource(int $type): string
    {
        return in_array($type, [self::TYPE_BUY, self::TYPE_RETURN_SUPPLIER, self::TYPE_PRODUCTION], true)
            ? 'cost'
            : 'price';
    }

    public static function typeIsNegative(int $type): bool
    {
        return in_array($type, [
            self::TYPE_SELL,
            self::TYPE_RETURN_SUPPLIER,
            self::TYPE_CASH_OUT,
            self::TYPE_TRANSFER,
            self::TYPE_MOVE,
        ], true);
    }

    /**
     * Signed monetary amount for header `total` per transaction type.
     * Negative: sell, return-supplier, cash out, transfer, move.
     * Positive: buy, return, cash in, adjustment.
     */
    public static function signedAmount(int $type, float $amount): float
    {
        if (self::typeIsNegative($type)) {
            return -abs($amount);
        }

        if (in_array($type, [self::TYPE_BUY, self::TYPE_RETURN, self::TYPE_CASH_IN, self::TYPE_ADJUST], true)) {
            return abs($amount);
        }

        return $amount;
    }

    public static function typeHasItems(int $type): bool
    {
        return in_array($type, [
            self::TYPE_BUY, self::TYPE_SELL, self::TYPE_MOVE,
            self::TYPE_RETURN, self::TYPE_RETURN_SUPPLIER,
            self::TYPE_PRODUCTION, self::TYPE_USE,
        ], true);
    }

    public static function dailyReportColumn(int $type): ?string
    {
        return match ($type) {
            self::TYPE_BUY => 'buy',
            self::TYPE_SELL => 'sell',
            self::TYPE_RETURN => 'return',
            self::TYPE_RETURN_SUPPLIER => 'return_supplier',
            self::TYPE_MOVE => 'move',
            self::TYPE_TRANSFER => 'transfer',
            self::TYPE_ADJUST => 'adjust',
            self::TYPE_PRODUCTION => 'use',
            self::TYPE_CASH_IN => 'sell',
            self::TYPE_CASH_OUT => 'buy',
            self::TYPE_DEPRECIATION => 'depreciation',
            default => null,
        };
    }

    /** @return array<int, array{id: int, name: string}> */
    public static function getTypes(): array
    {
        $ids = [
            self::TYPE_BUY, self::TYPE_SELL, self::TYPE_MOVE, self::TYPE_TRANSFER,
            self::TYPE_CASH_OUT, self::TYPE_USE, self::TYPE_CASH_IN, self::TYPE_ADJUST,
            self::TYPE_RETURN, self::TYPE_PRODUCTION, self::TYPE_RETURN_SUPPLIER, self::TYPE_DEPRECIATION,
        ];

        return array_map(
            fn (int $id) => ['id' => $id, 'name' => self::typeLabel($id)],
            $ids,
        );
    }

    public function getTypeLabel(): string
    {
        return self::typeLabel((int) $this->type);
    }

    public function getSubmitTypeLabel(): string
    {
        return match ((int) $this->submit_type) {
            self::SUBMIT_TYPE_MANUAL => 'Manual', self::SUBMIT_TYPE_JUBELIO => 'Jubelio Sync', default => 'Unknown'
        };
    }

    public function isManual(): bool
    {
        return (int) $this->submit_type === self::SUBMIT_TYPE_MANUAL;
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

    /**
     * Reorder loaded line items by item SKU (`items.code`) for display.
     * No-op when details are empty or the relation is not loaded.
     */
    public function sortDetailsBySku(): static
    {
        if (! $this->relationLoaded('details') || $this->details->isEmpty()) {
            return $this;
        }

        $this->setRelation(
            'details',
            $this->details
                ->sortBy(fn ($detail) => (string) ($detail->item?->code ?? ''), SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
        );

        return $this;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** User who pushed/confirmed sender-warehouse (side A) stock to Jubelio. */
    public function submitByA()
    {
        return $this->belongsTo(User::class, 'a_submit_by');
    }

    /** User who pushed/confirmed receiver-warehouse (side B) stock to Jubelio. */
    public function submitByB()
    {
        return $this->belongsTo(User::class, 'b_submit_by');
    }

    /** Sender warehouse was pushed to Jubelio but no item_adj_id was stored. */
    public function hasSyncWarningA(): bool
    {
        return $this->submit_a_count > 0 && $this->a_submit_by === null;
    }

    /** Receiver warehouse was pushed to Jubelio but no item_adj_id was stored. */
    public function hasSyncWarningB(): bool
    {
        return $this->submit_b_count > 0 && $this->b_submit_by === null;
    }

    public function isJubelioSenderSynced(): bool
    {
        return $this->a_submit_by !== null;
    }

    public function isJubelioReceiverSynced(): bool
    {
        return $this->b_submit_by !== null;
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
            'type-depreciation' => 'transactions-type-depreciation',
            'transaction-sync' => 'transactions-transaction-sync',
            'edit-invoice' => 'transactions-edit-invoice',
        ];
    }

    public static function typePermissionKey(string $typeSlug): string
    {
        return 'type-'.$typeSlug;
    }

    public static function permissionNameForType(string $typeSlug): ?string
    {
        return self::getPermissions()[self::typePermissionKey($typeSlug)] ?? null;
    }

    public static function userCanAccessType(?User $user, string $typeSlug): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        $permissions = self::getPermissions();
        $typePermission = self::permissionNameForType($typeSlug);

        if ($typePermission && $user->can($typePermission)) {
            return true;
        }

        return $user->can($permissions['view']) || $user->can($permissions['create']);
    }

    public static function userCanJubelioTransactionSync(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        $permissions = self::getPermissions();

        return $user->can($permissions['transaction-sync'])
            || $user->can($permissions['show'])
            || $user->can(Jubelio::getPermissions()['sync']);
    }

    public static function authorizeJubelioTransactionSync(): void
    {
        if (self::userCanJubelioTransactionSync(auth()->user())) {
            return;
        }

        Gate::authorize(self::getPermissions()['transaction-sync']);
    }

    public static function authorizeTypeAccess(string $typeSlug): void
    {
        if (self::userCanAccessType(auth()->user(), $typeSlug)) {
            return;
        }

        Gate::authorize(self::permissionNameForType($typeSlug) ?? self::getPermissions()['create']);
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            ProductionColumnDefaults::apply($transaction);

            $table = $transaction->getTable();

            if (Schema::hasColumn($table, 'description')) {
                $transaction->description ??= $transaction->notes ?? '';
            }

            if (Schema::hasColumn($table, 'due') && $transaction->due === null && $transaction->date !== null) {
                $transaction->due = $transaction->date;
            }

            if (Schema::hasColumn($table, 'detail_ids') && ($transaction->detail_ids === null || $transaction->detail_ids === '')) {
                $transaction->detail_ids = '';
            }

            if (Schema::hasColumn($table, 'cogs') && $transaction->cogs === null) {
                $transaction->cogs = 0;
            }

            if (Schema::hasColumn($table, 'location_id') && $transaction->location_id === null) {
                $transaction->location_id = auth()->user()?->location_id ?? 0;
            }

            if (Schema::hasColumn($table, 'submit_type') && $transaction->submit_type === null) {
                $transaction->submit_type = self::SUBMIT_TYPE_MANUAL;
            }
        });
    }
}
