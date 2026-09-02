<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FakturSellMatcher
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     invoice: string|null,
     *     dpp: float,
     *     ppn: float,
     *     warehouse_name: string,
     *     score: int,
     * }>
     */
    public function suggest(
        int $counterpartyId,
        ?string $fakturDate = null,
        ?string $fakturNumber = null,
        ?float $remainingDpp = null,
        ?int $excludeImportId = null,
        ?string $invoiceQuery = null,
        int $limit = 15,
    ): Collection {
        $linkedSellIds = $this->linkedSellIds($excludeImportId);

        $candidates = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('receiver_id', $counterpartyId)
            ->whereIn('receiver_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->when($linkedSellIds !== [], fn ($query) => $query->whereNotIn('id', $linkedSellIds))
            ->when($invoiceQuery, function ($query) use ($invoiceQuery) {
                $query->where(function ($inner) use ($invoiceQuery) {
                    $inner->where('invoice', 'like', '%'.$invoiceQuery.'%');
                    if (ctype_digit($invoiceQuery)) {
                        $inner->orWhere('id', (int) $invoiceQuery);
                    }
                });
            })
            ->when($fakturDate, function ($query) use ($fakturDate) {
                $from = Carbon::parse($fakturDate)->subDays(30)->toDateString();
                $to = Carbon::parse($fakturDate)->addDays(90)->toDateString();
                $query->whereBetween('date', [$from, $to]);
            }, fn ($query) => $query->where('date', '>=', now()->subMonths(6)->toDateString()))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $warehouseNames = Addrbook::withTrashed()
            ->whereIn('id', $candidates->pluck('sender_id')->unique())
            ->pluck('name', 'id');

        return $candidates
            ->map(function (Transaction $transaction) use ($fakturDate, $fakturNumber, $remainingDpp, $warehouseNames) {
                $score = 0;
                $dpp = abs((float) $transaction->total);

                if ($fakturNumber && $transaction->invoice && strcasecmp((string) $transaction->invoice, $fakturNumber) === 0) {
                    $score += 40;
                } elseif ($fakturNumber && $transaction->invoice && str_contains((string) $transaction->invoice, $fakturNumber)) {
                    $score += 15;
                }

                if ($remainingDpp !== null && $remainingDpp > 0 && abs($dpp - $remainingDpp) < 0.02) {
                    $score += 50;
                } elseif ($remainingDpp !== null && $remainingDpp > 0 && $dpp <= $remainingDpp + 0.02) {
                    $score += 20;
                }

                if ($fakturDate && $transaction->date?->toDateString() === $fakturDate) {
                    $score += 20;
                } elseif ($fakturDate) {
                    $daysApart = abs(Carbon::parse($fakturDate)->diffInDays($transaction->date));
                    if ($daysApart <= 14) {
                        $score += 10;
                    }
                }

                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date->toDateString(),
                    'invoice' => $transaction->invoice,
                    'dpp' => $dpp,
                    'ppn' => abs((float) $transaction->ppn),
                    'warehouse_name' => $warehouseNames[(int) $transaction->sender_id] ?? '—',
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * @return list<int>
     */
    private function linkedSellIds(?int $excludeImportId): array
    {
        $ids = [];

        if (Schema::hasTable('tax_faktur_import_sells')) {
            $ids = DB::table('tax_faktur_import_sells')
                ->when($excludeImportId, fn ($query) => $query->where('tax_faktur_import_id', '!=', $excludeImportId))
                ->pluck('sell_transaction_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $legacy = TaxFakturImport::query()
            ->whereNotNull('sell_transaction_id')
            ->when($excludeImportId, fn ($query) => $query->where('id', '!=', $excludeImportId))
            ->pluck('sell_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$ids, ...$legacy]));
    }
}
