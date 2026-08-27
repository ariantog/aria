<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaxFakturImport extends Model
{
    public const DIRECTION_KELUARAN = 'keluaran';

    public const DIRECTION_MASUKAN = 'masukan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'faktur_date' => 'date',
            'expected_payment_date' => 'date',
            'payment_received_date' => 'date',
            'gross_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'dpp' => 'decimal:2',
            'ppn' => 'decimal:2',
            'ppnbm' => 'decimal:2',
            'payment_received_amount' => 'decimal:2',
            'payment_variance' => 'decimal:2',
            'line_items' => 'array',
            'report_year' => 'integer',
            'report_month' => 'integer',
        ];
    }

    public function reportingEntity(): BelongsTo
    {
        return $this->belongsTo(ReportingEntity::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'counterparty_id');
    }

    public function varianceExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'variance_expense_addrbook_id');
    }

    public function cashInTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'cash_in_transaction_id');
    }

    public function varianceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'variance_transaction_id');
    }

    public function sellTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'sell_transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fakturGross(): float
    {
        return (float) $this->dpp + (float) $this->ppn + (float) $this->ppnbm;
    }

    public function paymentGraceDays(): int
    {
        return (int) ($this->counterparty?->payment_grace_days ?? 7);
    }

    public function paymentDeadline(): ?Carbon
    {
        if (! $this->expected_payment_date) {
            return null;
        }

        return $this->expected_payment_date->copy()->addDays($this->paymentGraceDays());
    }

    public function isPaymentOverdue(): bool
    {
        return app(\App\Services\Tax\ExpectedPaymentDateCalculator::class)->isOverdue(
            $this->expected_payment_date,
            $this->payment_received_date,
            $this->paymentGraceDays(),
        );
    }

    public function directionLabel(): string
    {
        return $this->direction === self::DIRECTION_MASUKAN ? 'Masukan (pembelian)' : 'Keluaran (penjualan)';
    }

    /**
     * MDS/Central-style consignment customers schedule payment via payment_due_day.
     */
    public function isConsignmentCounterparty(): bool
    {
        if ($this->direction !== self::DIRECTION_KELUARAN) {
            return false;
        }

        $counterparty = $this->counterparty;
        if (! $counterparty) {
            return false;
        }

        if (! in_array((int) $counterparty->type, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER], true)) {
            return false;
        }

        return $counterparty->payment_due_day !== null;
    }

    public function hasPaymentInfo(): bool
    {
        if ($this->cash_in_transaction_id) {
            return true;
        }

        return $this->payment_received_amount !== null
            && $this->payment_received_date !== null;
    }

    public function canPostConsignmentSell(): bool
    {
        return $this->isConsignmentCounterparty()
            && $this->hasPaymentInfo()
            && ! $this->sell_transaction_id;
    }

    public function scopePaymentOverdue(Builder $query): Builder
    {
        $today = now()->toDateString();

        $query->whereNull('payment_received_date')
            ->whereNotNull('expected_payment_date')
            ->join('customers as payment_cp', 'payment_cp.id', '=', 'tax_faktur_imports.counterparty_id');

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $query->whereRaw(
                "date(tax_faktur_imports.expected_payment_date, '+' || COALESCE(payment_cp.payment_grace_days, 7) || ' days') < ?",
                [$today],
            );
        }

        return $query->whereRaw(
            'DATE_ADD(tax_faktur_imports.expected_payment_date, INTERVAL COALESCE(payment_cp.payment_grace_days, 7) DAY) < ?',
            [$today],
        );
    }
}
