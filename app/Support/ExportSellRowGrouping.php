<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Carbon;

class ExportSellRowGrouping
{
    private ?int $previousTransactionId = null;

    /**
     * Transaction-level columns that should only appear on the first line per transaction.
     *
     * @return list<string>
     */
    public static function blankedOnRepeatColumnKeys(): array
    {
        return ['date', 'adjustment', 'discount', 'total', 'description'];
    }

    public function isFirstLineForTransaction(int $transactionId): bool
    {
        return $this->previousTransactionId !== $transactionId;
    }

    public function advance(int $transactionId): void
    {
        $this->previousTransactionId = $transactionId;
    }

    public function formattedDate(?TransactionDetail $detail, bool $isFirstLine): string
    {
        if (! $isFirstLine || ! $detail?->date) {
            return '';
        }

        return Carbon::parse($detail->date)->format('d/m/Y');
    }

    public function transactionColumnValue(string $columnKey, ?Transaction $transaction, bool $isFirstLine): string|float|int
    {
        if (! $isFirstLine) {
            return match ($columnKey) {
                'adjustment', 'discount', 'total' => '',
                'description' => '',
                default => '',
            };
        }

        return match ($columnKey) {
            'adjustment' => (float) ($transaction?->adjustment ?? 0),
            'discount' => (float) ($transaction?->discount ?? 0),
            'total' => (float) ($transaction?->total ?? 0),
            'description' => (string) ($transaction?->description ?: ($transaction?->notes ?? '')),
            default => '',
        };
    }
}
