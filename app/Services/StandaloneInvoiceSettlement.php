<?php

namespace App\Services;

use App\Models\StandaloneInvoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StandaloneInvoiceSettlement
{
    /**
     * Completed cash-in rows that share this invoice number count as payments.
     * Sender/receiver and bank transfers are ignored.
     *
     * @return Collection<int, Transaction>
     */
    public function cashIns(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsOfType($invoice->number, Transaction::TYPE_CASH_IN);
    }

    /**
     * Completed sell rows that share this invoice number. Several sells can
     * cover one invoice-maker invoice.
     *
     * @return Collection<int, Transaction>
     */
    public function sells(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsOfType($invoice->number, Transaction::TYPE_SELL);
    }

    /**
     * @param  list<string>  $numbers
     * @return array<string, array{cash_in: float, sell: float}>
     */
    public function totalsByNumbers(array $numbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $numbers,
        ), static fn (string $number) => $number !== '')));

        if ($numbers === []) {
            return [];
        }

        $rows = Transaction::query()
            ->whereIn('type', [Transaction::TYPE_CASH_IN, Transaction::TYPE_SELL])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereIn('invoice', $numbers)
            ->selectRaw('invoice, type, SUM(ABS(total)) as amount')
            ->groupBy('invoice', 'type')
            ->get();

        $totals = [];
        foreach ($numbers as $number) {
            $totals[$number] = ['cash_in' => 0.0, 'sell' => 0.0];
        }

        foreach ($rows as $row) {
            $key = (int) $row->type === Transaction::TYPE_SELL ? 'sell' : 'cash_in';
            $totals[(string) $row->invoice][$key] = (float) $row->amount;
        }

        return $totals;
    }

    /**
     * @return array{
     *     invoice: StandaloneInvoice,
     *     invoice_amount: float,
     *     due: float,
     *     paid_total: float,
     *     sell_total: float,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     amounts_match: bool,
     *     payments: Collection<int, Transaction>,
     *     sells: Collection<int, Transaction>,
     *     related: Collection<int, Transaction>
     * }|null
     */
    public function snapshotForTransaction(Transaction $transaction): ?array
    {
        $invoice = StandaloneInvoice::findByNumber($transaction->invoice);

        return $invoice ? $this->snapshot($invoice) : null;
    }

    /**
     * @return array{
     *     invoice: StandaloneInvoice,
     *     invoice_amount: float,
     *     due: float,
     *     paid_total: float,
     *     sell_total: float,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     amounts_match: bool,
     *     payments: Collection<int, Transaction>,
     *     sells: Collection<int, Transaction>,
     *     related: Collection<int, Transaction>
     * }
     */
    public function snapshot(StandaloneInvoice $invoice, ?float $paidTotal = null, ?float $sellTotal = null): array
    {
        $invoice->loadMissing('paidBy');
        $payments = $this->cashIns($invoice);
        $sells = $this->sells($invoice);
        $paidTotal ??= $this->sumAbsTotals($payments);
        $sellTotal ??= $this->sumAbsTotals($sells);
        $invoiceAmount = $invoice->billedAmount();
        $discount = round($invoice->discountAmount(), 2);
        $due = round($invoice->balanceDue(), 2);
        $amountsMatch = $this->amountsMatch($invoiceAmount, $paidTotal, $sellTotal);
        $status = $this->statusFromTotals($invoiceAmount, $paidTotal, $sellTotal);

        return [
            'invoice' => $invoice,
            'invoice_amount' => $invoiceAmount,
            'due' => $due,
            'paid_total' => round($paidTotal, 2),
            'sell_total' => round($sellTotal, 2),
            'discount' => $discount,
            'remaining' => round(max(0, $invoiceAmount - $paidTotal), 2),
            'status' => $status,
            'status_label' => StandaloneInvoice::STATUSES[$status] ?? $status,
            'is_paid' => $amountsMatch,
            'amounts_match' => $amountsMatch,
            'payments' => $payments,
            'sells' => $sells,
            'related' => collect(),
        ];
    }

    public function status(StandaloneInvoice $invoice, float $paidTotal, float $sellTotal = 0.0): string
    {
        return $this->statusFromTotals($invoice->billedAmount(), $paidTotal, $sellTotal);
    }

    public function updateDiscount(StandaloneInvoice $invoice, float $discount, ?User $user = null): StandaloneInvoice
    {
        $this->assertDiscount($invoice, $discount);

        $invoice->update(['discount_amount' => round($discount, 2)]);

        return $this->reconcile($invoice->fresh() ?? $invoice, $user);
    }

    public function reconcile(StandaloneInvoice $invoice, ?User $user = null): StandaloneInvoice
    {
        $snapshot = $this->snapshot($invoice);

        if ($snapshot['amounts_match']) {
            if (! $invoice->isMarkedPaid()) {
                $invoice->update([
                    'paid_at' => now(),
                    'paid_by' => $user?->id,
                ]);
            }
        } elseif ($invoice->isMarkedPaid()) {
            $invoice->update([
                'paid_at' => null,
                'paid_by' => null,
            ]);
        }

        return $invoice->fresh(['paidBy']) ?? $invoice;
    }

    public function reconcileByNumber(?string $number, ?User $user = null): ?StandaloneInvoice
    {
        $invoice = StandaloneInvoice::findByNumber($number);

        return $invoice ? $this->reconcile($invoice, $user) : null;
    }

    protected function statusFromTotals(float $invoiceAmount, float $paidTotal, float $sellTotal): string
    {
        if ($this->amountsMatch($invoiceAmount, $paidTotal, $sellTotal)) {
            return StandaloneInvoice::STATUS_PAID;
        }

        return ($paidTotal > 0 || $sellTotal > 0)
            ? StandaloneInvoice::STATUS_PARTIAL
            : StandaloneInvoice::STATUS_UNPAID;
    }

    protected function amountsMatch(float $invoiceAmount, float $paidTotal, float $sellTotal): bool
    {
        $invoiceAmount = round($invoiceAmount, 2);
        $paidTotal = round($paidTotal, 2);
        $sellTotal = round($sellTotal, 2);

        return $invoiceAmount > 0
            && $invoiceAmount === $paidTotal
            && $invoiceAmount === $sellTotal;
    }

    protected function assertDiscount(StandaloneInvoice $invoice, float $discount): void
    {
        if ($discount < 0) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot be negative.',
            ]);
        }

        $subtotal = round((float) $invoice->subtotal, 2);
        if (round($discount, 2) > $subtotal) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot exceed the invoice subtotal.',
            ]);
        }
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function completedTransactionsOfType(string $number, int $type): Collection
    {
        return Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('invoice', $number)
            ->where('type', $type)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    protected function sumAbsTotals(Collection $transactions): float
    {
        return (float) $transactions->sum(fn (Transaction $transaction) => abs((float) $transaction->total));
    }
}
