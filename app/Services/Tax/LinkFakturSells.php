<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LinkFakturSells
{
    /**
     * @param  list<int>  $sellTransactionIds
     */
    public function attach(TaxFakturImport $import, array $sellTransactionIds): TaxFakturImport
    {
        $ids = collect($sellTransactionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new InvalidArgumentException('Select at least one Sell to link.');
        }

        return DB::transaction(function () use ($import, $ids) {
            $import = TaxFakturImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            foreach ($ids as $sellId) {
                $this->assertCanLink($import, $sellId);
                $import->sellTransactions()->syncWithoutDetaching([$sellId]);
            }

            $this->syncLegacyColumn($import);

            return $import->fresh(['sellTransactions', 'sellTransaction']);
        });
    }

    public function detach(TaxFakturImport $import, int $sellTransactionId): TaxFakturImport
    {
        return DB::transaction(function () use ($import, $sellTransactionId) {
            $import = TaxFakturImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            $import->sellTransactions()->detach($sellTransactionId);
            $this->syncLegacyColumn($import);

            return $import->fresh(['sellTransactions', 'sellTransaction']);
        });
    }

    public function syncLegacyColumn(TaxFakturImport $import): void
    {
        $firstId = $import->sellTransactions()->orderBy('transactions.id')->value('transactions.id');
        $import->sell_transaction_id = $firstId ? (int) $firstId : null;
        $import->save();
    }

    private function assertCanLink(TaxFakturImport $import, int $sellTransactionId): void
    {
        $sell = Transaction::query()->find($sellTransactionId);
        if (! $sell || (int) $sell->type !== Transaction::TYPE_SELL) {
            throw new InvalidArgumentException('Linked transaction must be a Sell.');
        }

        if ((int) $sell->status !== Transaction::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Only completed Sells can be linked.');
        }

        if ((int) $sell->receiver_id !== (int) $import->counterparty_id) {
            throw new InvalidArgumentException('Sell customer must match faktur counterparty.');
        }

        if (! in_array((int) $sell->receiver_type, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER], true)) {
            throw new InvalidArgumentException('Sell receiver must be a customer or reseller.');
        }

        $alreadyOnOther = DB::table('tax_faktur_import_sells')
            ->where('sell_transaction_id', $sellTransactionId)
            ->where('tax_faktur_import_id', '!=', $import->id)
            ->exists();

        if ($alreadyOnOther) {
            throw new InvalidArgumentException('Sell is already linked to another faktur import.');
        }
    }
}
