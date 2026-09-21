<?php

namespace App\Actions\Transactions;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Services\StandaloneInvoiceSettlement;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateCashInFromFaktur
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * @param  array{date?: string|null, account_id: int, amount: float}  $data
     */
    public function execute(TaxFakturImport $import, array $data): Transaction
    {
        $import->loadMissing('counterparty');
        $this->assertCanCreate($import);

        $contact = $import->counterparty;
        if (! $contact || ! in_array((int) $contact->type, Addrbook::cashPartyTypes(), true)) {
            throw new InvalidArgumentException('Lawan transaksi harus customer, reseller, supplier, atau ledger.');
        }

        $account = Addrbook::query()->findOrFail((int) $data['account_id']);
        if ((int) $account->type !== Addrbook::TYPE_BANK) {
            throw new InvalidArgumentException('Pilih akun bank.');
        }

        $amount = (float) $data['amount'];
        if ($amount < 0.01) {
            throw new InvalidArgumentException('Jumlah Cash In minimal 0.01.');
        }

        $date = ! empty($data['date'])
            ? Carbon::parse((string) $data['date'])->toDateString()
            : Carbon::today()->toDateString();
        $grandTotal = Transaction::signedAmount(Transaction::TYPE_CASH_IN, $amount);

        return DB::transaction(function () use ($import, $contact, $account, $date, $grandTotal) {
            $trx = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_CASH_IN,
                'sender_type' => (int) $contact->type,
                'sender_id' => $contact->id,
                'receiver_type' => (int) $account->type,
                'receiver_id' => $account->id,
                'invoice' => $import->faktur_number,
                'notes' => sprintf('Cash In dari faktur %s', $import->faktur_number),
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => $grandTotal,
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'ppn_dpp' => null,
                'pph' => null,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            $this->transactionService->handleTransaction($trx);
            app(StandaloneInvoiceSettlement::class)
                ->reconcileByNumber((string) $trx->invoice, Auth::user());

            return $trx->fresh(['sender', 'receiver']) ?? $trx;
        });
    }

    private function assertCanCreate(TaxFakturImport $import): void
    {
        if ($import->direction !== TaxFakturImport::DIRECTION_KELUARAN) {
            throw new InvalidArgumentException('Hanya faktur keluaran yang bisa membuat Cash In.');
        }

        if ($import->cash_in_transaction_id) {
            throw new InvalidArgumentException('Faktur ini sudah punya Cash In terkait.');
        }
    }
}
