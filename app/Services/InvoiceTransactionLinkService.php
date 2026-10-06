<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class InvoiceTransactionLinkService
{
    /** @var list<int> */
    public const CREDIT_TYPES = [
        Transaction::TYPE_CASH_IN,
        Transaction::TYPE_RETURN,
    ];

    /** @var list<int> */
    public const DEBIT_TYPES = [
        Transaction::TYPE_SELL,
        Transaction::TYPE_CASH_OUT,
    ];

    /**
     * @return array{
     *     cash_in: float,
     *     return: float,
     *     sell: float,
     *     cash_out: float,
     *     credit: float,
     *     debit: float,
     *     is_complete: bool
     * }
     */
    public function totalsForInvoice(string $invoiceNumber): array
    {
        $numbers = $this->invoiceNumbersFor($invoiceNumber);

        $cashIn = $this->sumAbsForTypes($numbers, [Transaction::TYPE_CASH_IN]);
        $return = $this->sumAbsForTypes($numbers, [Transaction::TYPE_RETURN]);
        $sell = $this->sumAbsForTypes($numbers, [Transaction::TYPE_SELL]);
        $cashOut = $this->sumAbsForTypes($numbers, [Transaction::TYPE_CASH_OUT]);
        $credit = round($cashIn + $return, 2);
        $debit = round($sell + $cashOut, 2);

        return [
            'cash_in' => $cashIn,
            'return' => $return,
            'sell' => $sell,
            'cash_out' => $cashOut,
            'credit' => $credit,
            'debit' => $debit,
            'is_complete' => $this->totalsAreComplete($credit, $debit),
        ];
    }

    public function isLinkingComplete(string $invoiceNumber): bool
    {
        return $this->totalsForInvoice($invoiceNumber)['is_complete'];
    }

    /**
     * @return list<string>
     */
    public function invoiceNumbersFor(string $invoiceNumber, ?Transaction $anchor = null): array
    {
        $numbers = array_values(array_unique(array_filter([
            trim($invoiceNumber),
            $anchor ? (string) $anchor->id : null,
        ], static fn ($number) => trim((string) $number) !== '')));

        return $numbers;
    }

    /**
     * @param  list<string>  $numbers
     * @param  list<int>  $types
     */
    public function sumAbsForTypes(array $numbers, array $types): float
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $numbers,
        ), static fn (string $number) => $number !== '')));

        if ($numbers === [] || $types === []) {
            return 0.0;
        }

        $rows = Transaction::query()
            ->whereIn('type', $types)
            ->countsInReporting()
            ->whereIn('invoice', $numbers)
            ->selectRaw('SUM(ABS(total)) as amount')
            ->value('amount');

        return round((float) ($rows ?? 0), 2);
    }

    /**
     * @param  list<string>  $numbers
     * @param  list<int>  $types
     * @return Collection<int, Transaction>
     */
    public function transactionsForTypes(array $numbers, array $types): Collection
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $numbers,
        ), static fn (string $number) => $number !== '')));

        if ($numbers === [] || $types === []) {
            return collect();
        }

        return Transaction::query()
            ->with(['sender', 'receiver'])
            ->whereIn('type', $types)
            ->whereIn('invoice', $numbers)
            ->countsInReporting()
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    public function sumAbsTotals(Collection $transactions): float
    {
        return round((float) $transactions->sum(fn (Transaction $transaction) => abs((float) $transaction->total)), 2);
    }

    protected function totalsAreComplete(float $credit, float $debit): bool
    {
        $credit = round($credit, 2);
        $debit = round($debit, 2);

        return $credit > 0 && $credit === $debit;
    }
}
