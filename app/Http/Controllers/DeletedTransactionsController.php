<?php

namespace App\Http\Controllers;

use App\Models\DeletedTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DeletedTransactionsController extends Controller
{
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize(Transaction::getPermissions()['view']);

        $transactions = DeletedTransaction::with(['sender', 'receiver'])
            ->latest(DeletedTransaction::archivedAtColumn())
            ->paginate(10)
            ->withQueryString();

        return view('transactions.deleted', [
            'transactions' => $transactions,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function show($id)
    {
        \Illuminate\Support\Facades\Gate::authorize(Transaction::getPermissions()['view']);

        $transaction = DeletedTransaction::with(['details.item.group', 'sender', 'receiver'])->findOrFail($id);

        $typeKey = collect(config('transaction_rules'))->firstWhere('id', $transaction->type);
        $typeSlug = $typeKey ? array_search($typeKey, config('transaction_rules')) : 'adjust';

        $config = config("transaction_rules.{$typeSlug}");

        $getLabel = function ($role) use ($config) {
            if (isset($config[$role.'_type'])) {
                $types = collect(\App\Models\Addrbook::getTypes());
                $type = $types->firstWhere('id', $config[$role.'_type']);

                return $type ? $type['name'] : 'Contact';
            }

            return 'Contact';
        };

        return view('transactions.deleted-show', [
            'transaction' => $transaction,
            'config' => [
                'sender_label' => $getLabel('sender'),
                'receiver_label' => $getLabel('receiver'),
                'type_slug' => $typeSlug,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
