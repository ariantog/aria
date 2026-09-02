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
        $data['linked'] = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('invoice', $transaction->invoice)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $data;
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
