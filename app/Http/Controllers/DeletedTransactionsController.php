<?php

namespace App\Http\Controllers;

use App\Models\DeletedTransaction;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeletedTransactionsController extends Controller
{
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize(Transaction::getPermissions()['view']);

        $transactions = DeletedTransaction::with(['sender', 'receiver'])
            ->latest('deleted_at')
            ->paginate(10)
            ->withQueryString();

        return inertia('Transactions/DeletedIndex', [
            'transactions' => $transactions,
        ]);
    }

    public function show($id)
    {
        \Illuminate\Support\Facades\Gate::authorize(Transaction::getPermissions()['view']);

        $transaction = DeletedTransaction::with(['details.item', 'sender', 'receiver'])->findOrFail($id);

        // Find transaction type key from ID
        $typeKey = collect(config('transaction_rules'))->firstWhere('id', $transaction->type);
        $typeSlug = $typeKey ? array_search($typeKey, config('transaction_rules')) : 'adjust';

        $config = config("transaction_rules.{$typeSlug}");

        // Helper to get label
        $getLabel = function ($role) use ($config) {
            if (isset($config[$role.'_type'])) {
                $types = collect(\App\Models\Addrbook::getTypes());
                $type = $types->firstWhere('id', $config[$role.'_type']);

                return $type ? $type['name'] : 'Contact';
            }

            return 'Contact';
        };

        return inertia('Transactions/DeletedShow', [
            'transaction' => $transaction,
            'config' => [
                'sender_label' => $getLabel('sender'),
                'receiver_label' => $getLabel('receiver'),
                'type_slug' => $typeSlug,
            ],
            'can' => [
                'restore' => \Illuminate\Support\Facades\Gate::allows(Transaction::getPermissions()['delete']),
            ],
        ]);
    }

    public function restore($id, TransactionService $service)
    {
        \Illuminate\Support\Facades\Gate::authorize(Transaction::getPermissions()['delete']);

        $deletedTransaction = DeletedTransaction::with('details')->findOrFail($id);

        DB::transaction(function () use ($deletedTransaction, $service) {
            // 1. Copy DeletedTransaction back to Transaction
            $transactionData = $deletedTransaction->getAttributes();
            unset($transactionData['deleted_at']);

            $transaction = new Transaction;
            $transaction->forceFill($transactionData);
            $transaction->save();

            // 2. Copy DeletedTransactionDetail back to TransactionDetail
            foreach ($deletedTransaction->details as $detail) {
                $detailData = $detail->getAttributes();
                unset($detailData['deleted_at']);

                $newDetail = new TransactionDetail;
                $newDetail->forceFill($detailData);
                $newDetail->save();
            }

            // 3. Physically remove from deleted tables
            $deletedTransaction->details()->delete();
            $deletedTransaction->delete();

            // 4. Re-apply side effects (stock, balance, etc.)
            $service->handleTransaction($transaction);
        });

        return redirect()->route('transactions.deleted.index')->with('success', 'Transaction restored.');
    }
}
