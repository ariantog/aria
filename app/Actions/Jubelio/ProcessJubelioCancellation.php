<?php

namespace App\Actions\Jubelio;

use App\Models\Jubelioreturn;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessJubelioCancellation
{
    public function __construct(private TransactionService $transactionService) {}

    /**
     * @param  list<int>  $itemIds
     * @return array{success: bool, message: string}
     */
    public function execute(Jubelioreturn $return, array $itemIds, float $adjustment, int $userId): array
    {
        if ($return->status !== 0) {
            return ['success' => false, 'message' => 'Pembatalan ini sudah diproses.'];
        }

        if ($itemIds === []) {
            return ['success' => false, 'message' => 'Item belum dipilih.'];
        }

        $sellTransaction = Transaction::with(['details.item'])
            ->find($return->transaction_id);

        if (! $sellTransaction) {
            return ['success' => false, 'message' => 'Transaksi jual tidak ditemukan.'];
        }

        if ((int) $sellTransaction->jubelio_return > 0) {
            return ['success' => false, 'message' => 'Transaksi jual sudah pernah diretur.'];
        }

        $details = TransactionDetail::with('item')
            ->where('transaction_id', $sellTransaction->id)
            ->whereIn('item_id', $itemIds)
            ->get();

        if ($details->isEmpty()) {
            return ['success' => false, 'message' => 'Item tidak valid.'];
        }

        try {
            DB::transaction(function () use ($return, $sellTransaction, $details, $adjustment, $userId) {
                $transaction = new Transaction;
                $transaction->date = Carbon::now()->toDateString();
                $transaction->type = Transaction::TYPE_RETURN;
                $transaction->adjustment = $adjustment;
                $transaction->user_id = $userId;
                $transaction->submit_type = Transaction::SUBMIT_TYPE_MANUAL;
                $transaction->description = ' ';
                $transaction->notes = 'Jubelio cancellation return';
                $transaction->invoice = $sellTransaction->invoice;
                $transaction->due = null;
                $transaction->status = Transaction::STATUS_COMPLETED;
                $transaction->sender_id = $sellTransaction->receiver_id;
                $transaction->sender_type = $sellTransaction->receiver_type;
                $transaction->receiver_id = $sellTransaction->sender_id;
                $transaction->receiver_type = $sellTransaction->sender_type;
                $transaction->save();

                $totalQty = 0;
                $subTotal = 0;

                foreach ($details as $detail) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->date,
                        'transaction_type' => Transaction::TYPE_RETURN,
                        'sender_id' => $transaction->sender_id,
                        'receiver_id' => $transaction->receiver_id,
                        'item_id' => $detail->item_id,
                        'quantity' => $detail->quantity,
                        'price' => $detail->price,
                        'discount' => 0,
                        'total' => $detail->quantity * $detail->price,
                    ]);
                    $totalQty += $detail->quantity;
                    $subTotal += $detail->quantity * $detail->price;
                }

                $grandTotal = $subTotal + $transaction->adjustment;
                $transaction->total = Transaction::signedAmount(Transaction::TYPE_RETURN, $grandTotal);
                $transaction->total_items = $totalQty;
                $transaction->save();

                $this->transactionService->handleTransaction($transaction);

                $sellTransaction->update(['jubelio_return' => 2]);
                $return->update([
                    'status' => 1,
                    'confirmed_by' => $userId,
                ]);
            });

            return ['success' => true, 'message' => 'Return berhasil dibuat.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
