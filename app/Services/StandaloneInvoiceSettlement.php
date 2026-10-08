<?php

namespace App\Services;

use App\Models\StandaloneInvoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StandaloneInvoiceSettlement
{
    public function __construct(
        private readonly InvoiceTransactionLinkService $invoiceLinks,
    ) {}
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
     * @return Collection<int, Transaction>
     */
    public function returns(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsOfType($invoice->number, Transaction::TYPE_RETURN);
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function cashOuts(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsOfType($invoice->number, Transaction::TYPE_CASH_OUT);
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
            ->whereIn('type', [
                Transaction::TYPE_CASH_IN,
                Transaction::TYPE_RETURN,
                Transaction::TYPE_SELL,
                Transaction::TYPE_CASH_OUT,
            ])
            ->countsInReporting()
            ->whereIn('invoice', $numbers)
            ->selectRaw('invoice, type, SUM(ABS(total)) as amount')
            ->groupBy('invoice', 'type')
            ->get();

        $totals = [];
        foreach ($numbers as $number) {
            $totals[$number] = [
                'cash_in' => 0.0,
                'return' => 0.0,
                'sell' => 0.0,
                'cash_out' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $invoice = (string) $row->invoice;
            $key = match ((int) $row->type) {
                Transaction::TYPE_SELL => 'sell',
                Transaction::TYPE_CASH_OUT => 'cash_out',
                Transaction::TYPE_RETURN => 'return',
                default => 'cash_in',
            };
            $totals[$invoice][$key] = (float) $row->amount;
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
     *     return_total: float,
     *     cash_out_total: float,
     *     credit_total: float,
     *     debit_total: float,
     *     linking_complete: bool,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     amounts_match: bool,
     *     payments: Collection<int, Transaction>,
     *     sells: Collection<int, Transaction>,
     *     returns: Collection<int, Transaction>,
     *     cash_outs: Collection<int, Transaction>,
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
     *     return_total: float,
     *     cash_out_total: float,
     *     credit_total: float,
     *     debit_total: float,
     *     linking_complete: bool,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     amounts_match: bool,
     *     payments: Collection<int, Transaction>,
     *     sells: Collection<int, Transaction>,
     *     returns: Collection<int, Transaction>,
     *     cash_outs: Collection<int, Transaction>,
     *     related: Collection<int, Transaction>
     * }
     */
    public function snapshot(StandaloneInvoice $invoice, ?float $paidTotal = null, ?float $sellTotal = null): array
    {
        $invoice->loadMissing('paidBy');
        $payments = $this->cashIns($invoice);
        $sells = $this->sells($invoice);
        $returns = $this->returns($invoice);
        $cashOuts = $this->cashOuts($invoice);
        $paidTotal ??= $this->sumAbsTotals($payments);
        $sellTotal ??= $this->sumAbsTotals($sells);
        $returnTotal = $this->sumAbsTotals($returns);
        $cashOutTotal = $this->sumAbsTotals($cashOuts);
        $linkTotals = $this->invoiceLinks->totalsForInvoice($invoice->number);
        $creditTotal = $linkTotals['credit'];
        $debitTotal = $linkTotals['debit'];
        $linkingComplete = $linkTotals['is_complete'];
        $invoiceAmount = $invoice->billedAmount();
        $discount = round($invoice->discountAmount(), 2);
        $due = round($invoice->balanceDue(), 2);
        $amountsMatch = $this->amountsMatch($invoiceAmount, $creditTotal, $debitTotal, $linkingComplete, $discount);
        $status = $this->statusFromTotals($invoiceAmount, $creditTotal, $debitTotal, $linkingComplete, $discount);

        return [
            'invoice' => $invoice,
            'invoice_amount' => $invoiceAmount,
            'due' => $due,
            'paid_total' => round($paidTotal, 2),
            'sell_total' => round($sellTotal, 2),
            'return_total' => round($returnTotal, 2),
            'cash_out_total' => round($cashOutTotal, 2),
            'credit_total' => $creditTotal,
            'debit_total' => $debitTotal,
            'linking_complete' => $linkingComplete,
            'discount' => $discount,
            'remaining' => round(max(0, $invoiceAmount - $paidTotal), 2),
            'status' => $status,
            'status_label' => StandaloneInvoice::STATUSES[$status] ?? $status,
            'is_paid' => $amountsMatch,
            'amounts_match' => $amountsMatch,
            'payments' => $payments,
            'sells' => $sells,
            'returns' => $returns,
            'cash_outs' => $cashOuts,
            'related' => collect(),
        ];
    }

    public function status(StandaloneInvoice $invoice, float $paidTotal, float $sellTotal = 0.0): string
    {
        $linkTotals = $this->invoiceLinks->totalsForInvoice($invoice->number);

        return $this->statusFromTotals(
            $invoice->billedAmount(),
            $linkTotals['credit'],
            $linkTotals['debit'],
            $linkTotals['is_complete'],
            round($invoice->discountAmount(), 2),
        );
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

    protected function statusFromTotals(
        float $invoiceAmount,
        float $creditTotal,
        float $debitTotal,
        bool $linkingComplete,
        float $discount = 0.0,
    ): string {
        if ($this->amountsMatch($invoiceAmount, $creditTotal, $debitTotal, $linkingComplete, $discount)) {
            return StandaloneInvoice::STATUS_PAID;
        }

        return ($creditTotal > 0 || $debitTotal > 0)
            ? StandaloneInvoice::STATUS_PARTIAL
            : StandaloneInvoice::STATUS_UNPAID;
    }

    protected function amountsMatch(
        float $invoiceAmount,
        float $creditTotal,
        float $debitTotal,
        bool $linkingComplete,
        float $discount = 0.0,
    ): bool {
        $invoiceAmount = round($invoiceAmount, 2);
        $creditTotal = round($creditTotal, 2);
        $debitTotal = round($debitTotal, 2);
        $discount = round($discount, 2);

        if ($invoiceAmount <= 0 || $invoiceAmount !== $creditTotal) {
            return false;
        }

        if ($linkingComplete && $invoiceAmount === $debitTotal) {
            return true;
        }

        return $discount > 0
            && $debitTotal > $creditTotal
            && round($debitTotal - $creditTotal, 2) === $discount;
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
            ->countsInReporting()
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
