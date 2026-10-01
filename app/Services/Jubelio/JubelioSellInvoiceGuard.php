<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use App\Models\Transaction;

class JubelioSellInvoiceGuard
{
    public function sellInvoiceInTransactions(string $invoice): bool
    {
        if ($invoice === '') {
            return false;
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice', $invoice)
            ->exists();
    }

    public function sellInvoiceInQueue(string $invoice, ?int $exceptOrderId = null): bool
    {
        if ($invoice === '') {
            return false;
        }

        return Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->when($exceptOrderId !== null, fn ($query) => $query->where('id', '!=', $exceptOrderId))
            ->exists();
    }

    public function sellInvoiceTaken(string $invoice, ?int $exceptOrderId = null): bool
    {
        return $this->sellInvoiceInTransactions($invoice)
            || $this->sellInvoiceInQueue($invoice, $exceptOrderId);
    }

    /**
     * @param  list<string>  $invoices
     * @return list<string>
     */
    public function filterInvoicesAlreadyTaken(array $invoices): array
    {
        if ($invoices === []) {
            return [];
        }

        $inTransactions = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->whereIn('invoice', $invoices)
            ->pluck('invoice')
            ->all();

        $inJubelio = Jubelioorder::query()
            ->where('type', 'SELL')
            ->whereIn('invoice', $invoices)
            ->pluck('invoice')
            ->all();

        return array_values(array_unique(array_merge($inTransactions, $inJubelio)));
    }
}
