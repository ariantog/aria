<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaxFakturImport extends Model
{
    public const DIRECTION_KELUARAN = 'keluaran';

    public const DIRECTION_MASUKAN = 'masukan';

    public const LINK_FILTER_UNLINKED = 'unlinked';

    public const LINK_FILTER_REMAINING = 'remaining';

    public const LINK_FILTER_INCOMPLETE = 'incomplete';

    /** Linked Sell DPP within this of faktur DPP is treated as complete. */
    public const SELL_DPP_TOLERANCE = 0.02;

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

    public function sellTransactions(): BelongsToMany
    {
        return $this->belongsToMany(
            Transaction::class,
            'tax_faktur_import_sells',
            'tax_faktur_import_id',
            'sell_transaction_id',
        )->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Invoice total payable: Harga Jual/Penggantian (minus potongan) + PPN + PPnBM.
     * DPP Nilai Lain is the tax base only — adding it to PPN understates the invoice.
     */
    public function fakturGross(): float
    {
        $netSellingPrice = (float) $this->gross_total - (float) $this->discount_total;

        return round(max(0, $netSellingPrice) + (float) $this->ppn + (float) $this->ppnbm, 2);
    }

    /**
     * Recompute selisih from stored payment vs current invoice total so older
     * imports (saved when total was DPP+PPN) display the correct gap.
     */
    public function computedPaymentVariance(): ?float
    {
        if ($this->payment_received_amount === null) {
            return $this->payment_variance !== null ? (float) $this->payment_variance : null;
        }

        return round((float) $this->payment_received_amount - $this->fakturGross(), 2);
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

    public function hasLinkedSells(): bool
    {
        if ($this->sell_transaction_id) {
            return true;
        }

        if (! Schema::hasTable('tax_faktur_import_sells')) {
            return false;
        }

        if ($this->relationLoaded('sellTransactions')) {
            return $this->sellTransactions->isNotEmpty();
        }

        return $this->sellTransactions()->exists();
    }

    public function linkedSellDpp(): float
    {
        return round($this->linkedSells()->sum(fn (Transaction $sell) => abs((float) $sell->total)), 2);
    }

    public function linkedSellPpn(): float
    {
        return round($this->linkedSells()->sum(fn (Transaction $sell) => abs((float) $sell->ppn)), 2);
    }

    public function remainingSellDpp(): float
    {
        return round(max(0, (float) $this->dpp - $this->linkedSellDpp()), 2);
    }

    public function hasShortLinkedDpp(): bool
    {
        return $this->hasLinkedSells()
            && $this->remainingSellDpp() > self::SELL_DPP_TOLERANCE;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    public function linkedSells()
    {
        if ($this->relationLoaded('sellTransactions')) {
            return $this->sellTransactions;
        }

        if (! Schema::hasTable('tax_faktur_import_sells')) {
            return collect();
        }

        return $this->sellTransactions()->orderBy('transactions.id')->get();
    }

    public function canPostConsignmentSell(): bool
    {
        return $this->isConsignmentCounterparty()
            && $this->hasPaymentInfo()
            && ! $this->hasLinkedSells();
    }

    public function scopeWithoutLinkedSells(Builder $query): Builder
    {
        $query->whereNull('tax_faktur_imports.sell_transaction_id');

        if (Schema::hasTable('tax_faktur_import_sells')) {
            $query->whereDoesntHave('sellTransactions');
        }

        return $query;
    }

    /**
     * Keluaran with no Sell on the pivot or the legacy single-id column.
     */
    public function scopeKeluaranWithoutLinkedSells(Builder $query): Builder
    {
        return $query
            ->where('tax_faktur_imports.direction', self::DIRECTION_KELUARAN)
            ->withoutLinkedSells();
    }

    /**
     * Keluaran that already have at least one linked Sell whose abs(total)
     * still sums short of faktur DPP. Does not change PPN ringkasan — Sell
     * ledger remains truth; this is a staff work-queue only.
     */
    public function scopeKeluaranWithShortLinkedDpp(Builder $query): Builder
    {
        $query->where('tax_faktur_imports.direction', self::DIRECTION_KELUARAN);

        if (! Schema::hasTable('tax_faktur_import_sells')) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereHas('sellTransactions')
            ->whereRaw(self::shortLinkedDppSql());
    }

    /**
     * Work queue: keluaran with no Sells, or with linked DPP still short.
     */
    public function scopeKeluaranNeedingSellCoverage(Builder $query): Builder
    {
        return $query
            ->where('tax_faktur_imports.direction', self::DIRECTION_KELUARAN)
            ->where(function (Builder $inner) {
                $inner->where(function (Builder $unlinked) {
                    $unlinked->withoutLinkedSells();
                });

                if (Schema::hasTable('tax_faktur_import_sells')) {
                    $inner->orWhere(function (Builder $short) {
                        $short->whereHas('sellTransactions')
                            ->whereRaw(self::shortLinkedDppSql());
                    });
                }
            });
    }

    public function scopeLinkFilter(Builder $query, ?string $link): Builder
    {
        return match ($link) {
            self::LINK_FILTER_UNLINKED => $query->keluaranWithoutLinkedSells(),
            self::LINK_FILTER_REMAINING => $query->keluaranWithShortLinkedDpp(),
            self::LINK_FILTER_INCOMPLETE => $query->keluaranNeedingSellCoverage(),
            default => $query,
        };
    }

    /**
     * Correlated SUM of abs(transactions.total) for pivot-linked Sells.
     * No FK to transactions — the pivot stores the id + index only.
     */
    private static function shortLinkedDppSql(): string
    {
        $tolerance = self::SELL_DPP_TOLERANCE;

        return "(SELECT COALESCE(SUM(ABS(tfs_sell.total)), 0)
            FROM tax_faktur_import_sells AS tfs_dpp
            INNER JOIN transactions AS tfs_sell ON tfs_sell.id = tfs_dpp.sell_transaction_id
            WHERE tfs_dpp.tax_faktur_import_id = tax_faktur_imports.id
        ) < tax_faktur_imports.dpp - {$tolerance}";
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
