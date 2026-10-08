<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class SellCashInPresenter
{
    public function __construct(
        private readonly BookClosingService $bookClosing,
        private readonly UserPreferenceService $userPreferences,
        private readonly InvoiceTransactionLinkService $invoiceLinks,
    ) {}

    public function userCanCreate(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return $user->can(Transaction::getPermissions()['type-cash-in']);
    }

    /**
     * @return array{
     *     can_create: bool,
     *     banks: Collection<int, Addrbook>,
     *     default_account: array{id: int, name: string}|null,
     *     min_date: string,
     *     default_date: string,
     *     default_amount: float,
     *     paid_total: float,
     *     remaining: float,
     *     sell_total: float,
     *     linked: Collection<int, Transaction>
     * }
     */
    public function formData(?User $user, float $defaultAmount = 0.0): array
    {
        $today = now()->toDateString();
        $minDate = $this->bookClosing->getMinAllowedDate()->toDateString();

        return [
            'can_create' => $this->userCanCreate($user),
            'banks' => Addrbook::query()
                ->where('type', Addrbook::TYPE_BANK)
                ->orderBy('name')
                ->get(),
            'default_account' => $user
                ? $this->userPreferences->defaultCashAccount($user, true)
                : null,
            'min_date' => $minDate,
            'default_date' => $today < $minDate ? $minDate : $today,
            'default_amount' => $defaultAmount,
            'paid_total' => 0.0,
            'remaining' => $defaultAmount,
            'sell_total' => $defaultAmount,
            'linked' => collect(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $invoiceSettlement
     * @return array<string, mixed>|null
     */
    public function forSell(Transaction $transaction, ?User $user, ?array $invoiceSettlement = null): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_SELL) {
            return null;
        }

        $sellTotal = $transaction->displayGrandTotal();
        $linkedCashIns = $this->linkedCashInsForSell($transaction, $invoiceSettlement);
        $paidTotal = $this->sumAbsTotals($linkedCashIns);
        $sellRemaining = round(max(0, $sellTotal - $paidTotal), 2);
        $linkingComplete = $this->invoiceLinks->isLinkingComplete((string) $transaction->invoice);

        $invoiceAmount = $invoiceSettlement
            ? round((float) ($invoiceSettlement['invoice_amount'] ?? 0), 2)
            : 0.0;
        $invoicePaidCompleted = $invoiceSettlement
            ? $this->completedCashInTotalFromSettlement($invoiceSettlement)
            : 0.0;
        $invoiceRemaining = $invoiceSettlement
            ? round(max(0, $invoiceAmount - $invoicePaidCompleted), 2)
            : 0.0;

        $defaultAmount = $sellRemaining;
        if ($invoiceSettlement && $invoiceRemaining > 0.009) {
            $defaultAmount = $sellRemaining > 0.009
                ? min($sellRemaining, $invoiceRemaining)
                : $invoiceRemaining;
        }

        $data = $this->formData($user, $defaultAmount);
        if ($invoiceSettlement) {
            $invoice = $invoiceSettlement['invoice'] ?? null;
            $data['can_create'] = $data['can_create']
                && (int) $transaction->status !== Transaction::STATUS_CANCELLED
                && $invoiceRemaining > 0.009;
            $data['auto_enable'] = true;
            $data['hide_date'] = true;
            $data['return_invoice_id'] = $invoice?->id;
            $data['paid_total'] = $invoicePaidCompleted;
            $data['remaining'] = $invoiceRemaining;
            $data['sell_total'] = $invoiceAmount > 0.009 ? $invoiceAmount : $sellTotal;
        } else {
            $data['can_create'] = $data['can_create']
                && (int) $transaction->status !== Transaction::STATUS_CANCELLED
                && ! $linkingComplete;
            $data['paid_total'] = $paidTotal;
            $data['remaining'] = round($defaultAmount, 2);
            $data['sell_total'] = $sellTotal;
        }
        $data['linked'] = $linkedCashIns;

        return $data;
    }

    /**
     * @return array{title: string, linked: Collection<int, Transaction>, party: 'sender'|'receiver'}|null
     */
    public function forCashIn(Transaction $transaction): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_IN) {
            return null;
        }

        $linked = $this->linkedSellsForCashIn($transaction);

        if ($linked->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Linked sell',
            'linked' => $linked,
            'party' => 'receiver',
        ];
    }

    /**
     * @return array{title: string, linked: Collection<int, Transaction>, party: 'sender'|'receiver'}|null
     */
    public function forCashOut(Transaction $transaction): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_OUT) {
            return null;
        }

        $linked = $this->linkedTransactionsByNumbers(
            $this->invoiceLinks->invoiceNumbersFor((string) $transaction->invoice, $transaction),
            Transaction::TYPE_BUY,
        );

        if ($linked->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Linked buy',
            'linked' => $linked,
            'party' => 'receiver',
        ];
    }

    /**
     * @return array{title: string, linked: Collection<int, Transaction>, party: 'sender'|'receiver'}|null
     */
    public function forBuy(Transaction $transaction): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_BUY) {
            return null;
        }

        $linked = $this->linkedTransactionsByNumbers(
            $this->invoiceLinks->invoiceNumbersFor((string) $transaction->invoice, $transaction),
            Transaction::TYPE_CASH_OUT,
        );

        if ($linked->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Linked cash-out',
            'linked' => $linked,
            'party' => 'sender',
        ];
    }

    /**
     * @return array{title: string, linked: Collection<int, Transaction>, party: 'sender'|'receiver'}|null
     */
    public function forReturn(Transaction $transaction): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_RETURN) {
            return null;
        }

        $linked = $this->linkedTransactionsByNumbers(
            $this->invoiceLinks->invoiceNumbersFor((string) $transaction->invoice, $transaction),
            Transaction::TYPE_SELL,
        );

        if ($linked->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Linked sell',
            'linked' => $linked,
            'party' => 'receiver',
        ];
    }

    /**
     * Cash-ins linked to this sell match either the sell invoice or its transaction id.
     * Staff often edit a manual cash-in to the sell id while the sell keeps an invoice-maker number.
     *
     * @return Collection<int, Transaction>
     */
    private function linkedCashInsForSell(Transaction $sell, ?array $invoiceSettlement = null): Collection
    {
        $linked = $this->linkedTransactionsByNumbers(
            $this->invoiceLinks->invoiceNumbersFor((string) $sell->invoice, $sell),
            Transaction::TYPE_CASH_IN,
        );

        if ($invoiceSettlement) {
            $payments = $invoiceSettlement['payments'] ?? collect();
            if ($payments instanceof Collection && $payments->isNotEmpty()) {
                $linked = $linked
                    ->merge($payments)
                    ->unique(fn (Transaction $transaction) => $transaction->id)
                    ->values();
            }
        }

        return $this->sortLinkedTransactions($linked);
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function linkedSellsForCashIn(Transaction $cashIn): Collection
    {
        $numbers = $this->invoiceLinks->invoiceNumbersFor((string) $cashIn->invoice, $cashIn);
        $linked = $this->linkedTransactionsByNumbers($numbers, Transaction::TYPE_SELL);

        foreach ($numbers as $number) {
            if ($number !== '' && ctype_digit($number)) {
                $byId = Transaction::query()
                    ->with(['sender', 'receiver'])
                    ->where('type', Transaction::TYPE_SELL)
                    ->where('id', (int) $number)
                    ->countsInReporting()
                    ->get();

                $linked = $linked
                    ->merge($byId)
                    ->unique(fn (Transaction $transaction) => $transaction->id)
                    ->values();
            }
        }

        return $this->sortLinkedTransactions($linked);
    }

    /**
     * @param  list<string>  $numbers
     * @return Collection<int, Transaction>
     */
    private function linkedTransactionsByNumbers(array $numbers, int $type): Collection
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $numbers,
        ), static fn (string $number) => $number !== '')));

        if ($numbers === []) {
            return collect();
        }

        return Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('type', $type)
            ->whereIn('invoice', $numbers)
            ->countsInReporting()
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function linkedTransactions(string $invoice, int $type): Collection
    {
        return $this->linkedTransactionsByNumbers([$invoice], $type);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    private function sortLinkedTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->sortBy(fn (Transaction $transaction) => [
                $transaction->date?->format('Y-m-d') ?? '',
                $transaction->id,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $invoiceSettlement
     * @return array<string, mixed>|null
     */
    public function forInvoiceSettlement(array $invoiceSettlement, ?User $user): ?array
    {
        $sell = $this->primarySell($invoiceSettlement);
        if (! $sell) {
            return null;
        }

        return $this->forSell($sell, $user, $invoiceSettlement);
    }

    /**
     * @param  array<string, mixed>  $invoiceSettlement
     */
    public function primarySell(array $invoiceSettlement): ?Transaction
    {
        $sells = $invoiceSettlement['sells'] ?? collect();
        if (! $sells instanceof Collection) {
            $sells = collect($sells);
        }

        $sell = $sells->first();

        return $sell instanceof Transaction ? $sell : null;
    }

    public function cashInReceiver(Transaction $sell): ?Addrbook
    {
        if ((int) $sell->receiver_id < 1) {
            return null;
        }

        $receiver = $sell->relationLoaded('receiver')
            ? $sell->receiver
            : null;

        if ($receiver) {
            return $receiver;
        }

        return Addrbook::withTrashed()->find((int) $sell->receiver_id);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function sumAbsTotals(Collection $transactions): float
    {
        return (float) $transactions->sum(fn (Transaction $transaction) => abs((float) $transaction->total));
    }

    /**
     * Pending cash-ins count toward settlement status but not toward closing the invoice-maker form.
     *
     * @param  array<string, mixed>  $invoiceSettlement
     */
    private function completedCashInTotalFromSettlement(array $invoiceSettlement): float
    {
        $payments = $invoiceSettlement['payments'] ?? collect();
        if (! $payments instanceof Collection) {
            $payments = collect($payments);
        }

        return round((float) $payments
            ->filter(fn (Transaction $transaction) => (int) $transaction->status === Transaction::STATUS_COMPLETED)
            ->sum(fn (Transaction $transaction) => abs((float) $transaction->total)), 2);
    }
}
