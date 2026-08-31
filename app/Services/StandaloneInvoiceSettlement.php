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
     * Cash-in rows that share this invoice number count as payments.
     * Several receipts can settle one invoice-maker invoice.
     *
     * @return Collection<int, Transaction>
     */
    public function payments(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsQuery($invoice->number)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function relatedTransactions(StandaloneInvoice $invoice): Collection
    {
        return $this->completedTransactionsQuery($invoice->number)
            ->where('type', '!=', Transaction::TYPE_CASH_IN)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $numbers
     * @return array<string, float>
     */
    public function paymentTotalsByNumbers(array $numbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $numbers,
        ), static fn (string $number) => $number !== '')));

        if ($numbers === []) {
            return [];
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereIn('invoice', $numbers)
            ->selectRaw('invoice, SUM(ABS(total)) as paid_total')
            ->groupBy('invoice')
            ->pluck('paid_total', 'invoice')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    /**
     * @return array{
     *     invoice: StandaloneInvoice,
     *     due: float,
     *     paid_total: float,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     payments: Collection<int, Transaction>,
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
     *     due: float,
     *     paid_total: float,
     *     discount: float,
     *     remaining: float,
     *     status: string,
     *     status_label: string,
     *     is_paid: bool,
     *     payments: Collection<int, Transaction>,
     *     related: Collection<int, Transaction>
     * }
     */
    public function snapshot(StandaloneInvoice $invoice, ?float $paidTotal = null): array
    {
        $invoice->loadMissing('paidBy');
        $payments = $this->payments($invoice);
        $paidTotal ??= (float) $payments->sum(fn (Transaction $transaction) => abs((float) $transaction->total));
        $due = round($invoice->balanceDue(), 2);
        $discount = round($invoice->discountAmount(), 2);
        $remaining = round(max(0, $due - $paidTotal - $discount), 2);
        $status = $this->status($invoice, $paidTotal);

        return [
            'invoice' => $invoice,
            'due' => $due,
            'paid_total' => round($paidTotal, 2),
            'discount' => $discount,
            'remaining' => $remaining,
            'status' => $status,
            'status_label' => StandaloneInvoice::STATUSES[$status] ?? $status,
            'is_paid' => $invoice->isMarkedPaid(),
            'payments' => $payments,
            'related' => $this->relatedTransactions($invoice),
        ];
    }

    public function status(StandaloneInvoice $invoice, float $paidTotal): string
    {
        if ($invoice->isMarkedPaid()) {
            return StandaloneInvoice::STATUS_PAID;
        }

        return $paidTotal > 0
            ? StandaloneInvoice::STATUS_PARTIAL
            : StandaloneInvoice::STATUS_UNPAID;
    }

    public function updateDiscount(StandaloneInvoice $invoice, float $discount): StandaloneInvoice
    {
        $this->assertDiscount($invoice, $discount);

        $invoice->update(['discount_amount' => round($discount, 2)]);

        return $invoice->fresh() ?? $invoice;
    }

    public function markPaid(StandaloneInvoice $invoice, User $user, ?float $discount = null): StandaloneInvoice
    {
        if ($discount !== null) {
            $invoice = $this->updateDiscount($invoice, $discount);
        }

        $snapshot = $this->snapshot($invoice);
        if ($snapshot['remaining'] > 0) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Payments plus discount must cover the invoice balance before it can be marked paid. Remaining: '.format_currency($snapshot['remaining']).'.',
            ]);
        }

        $invoice->update([
            'paid_at' => now(),
            'paid_by' => $user->id,
        ]);

        return $invoice->fresh(['paidBy']) ?? $invoice;
    }

    public function unmarkPaid(StandaloneInvoice $invoice): StandaloneInvoice
    {
        $invoice->update([
            'paid_at' => null,
            'paid_by' => null,
        ]);

        return $invoice->fresh() ?? $invoice;
    }

    protected function assertDiscount(StandaloneInvoice $invoice, float $discount): void
    {
        if ($discount < 0) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot be negative.',
            ]);
        }

        $due = round($invoice->balanceDue(), 2);
        if (round($discount, 2) > $due) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot exceed the invoice balance due.',
            ]);
        }
    }

    protected function completedTransactionsQuery(string $number)
    {
        return Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('invoice', $number)
            ->where('status', Transaction::STATUS_COMPLETED);
    }
}
