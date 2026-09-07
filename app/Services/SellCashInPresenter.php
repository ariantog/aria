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

        $defaultAmount = $transaction->displayGrandTotal();
        if ($invoiceSettlement && (float) ($invoiceSettlement['remaining'] ?? 0) > 0.009) {
            $defaultAmount = (float) $invoiceSettlement['remaining'];
        }

        $data = $this->formData($user, $defaultAmount);
        $data['can_create'] = $data['can_create']
            && (int) $transaction->status !== Transaction::STATUS_CANCELLED;
        $data['linked'] = $this->linkedTransactions(
            (string) $transaction->invoice,
            Transaction::TYPE_CASH_IN,
        );

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

        $linked = $this->linkedTransactions(
            (string) $transaction->invoice,
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
     * @return array{title: string, linked: Collection<int, Transaction>, party: 'sender'|'receiver'}|null
     */
    public function forCashOut(Transaction $transaction): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_CASH_OUT) {
            return null;
        }

        $linked = $this->linkedTransactions(
            (string) $transaction->invoice,
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

        $linked = $this->linkedTransactions(
            (string) $transaction->invoice,
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
     * @return Collection<int, Transaction>
     */
    private function linkedTransactions(string $invoice, int $type): Collection
    {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return collect();
        }

        return Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('type', $type)
            ->where('invoice', $invoice)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
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
}
