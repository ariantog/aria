<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use App\Models\Transaction;

class JubelioSellReturnGuard
{
    public function returnPostedForSell(string $sellInvoice): bool
    {
        if ($sellInvoice === '') {
            return false;
        }

        return Transaction::query()
            ->where('type', Transaction::TYPE_RETURN)
            ->where(function ($query) use ($sellInvoice) {
                $query->where('invoice', $sellInvoice)
                    ->orWhere('description', $sellInvoice);
            })
            ->exists();
    }

    public function sellAlreadyReturned(string $sellInvoice, ?int $exceptJubelioOrderId = null): bool
    {
        return $this->returnPostedForSell($sellInvoice)
            || $this->pendingJubelioReturnQueue($sellInvoice, $exceptJubelioOrderId);
    }

    public function pendingJubelioReturnQueue(string $sellInvoice, ?int $exceptJubelioOrderId = null): bool
    {
        if ($sellInvoice === '') {
            return false;
        }

        if ($this->hasSourceSellInvoiceColumn()) {
            return Jubelioorder::query()
                ->where('type', 'RETURN')
                ->where('source_sell_invoice', $sellInvoice)
                ->whereIn('status', [0, 1])
                ->when($exceptJubelioOrderId !== null, fn ($query) => $query->where('id', '!=', $exceptJubelioOrderId))
                ->exists();
        }

        return false;
    }

    private function hasSourceSellInvoiceColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('jubelioorders', 'source_sell_invoice');
        }

        return $hasColumn;
    }
}
