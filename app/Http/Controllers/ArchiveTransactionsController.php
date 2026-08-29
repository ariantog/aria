<?php

namespace App\Http\Controllers;

use App\Models\Archive\ArchiveTransaction;
use App\Models\DataRetentionRun;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ArchiveTransactionsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(DataRetentionRun::getPermissions()['archive-view']);

        $year = $request->query('year');
        $search = trim((string) $request->query('search', ''));

        $query = ArchiveTransaction::query()
            ->with(['sender', 'receiver'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($year !== null && $year !== '') {
            $y = (int) $year;
            $query->whereBetween('date', [sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $transactions = $query->paginate(20)->withQueryString();
        $typeBadges = $this->typeBadges();

        return view('archive.transactions.index', [
            'transactions' => $transactions,
            'typeBadges' => $typeBadges,
            'year' => $year,
            'search' => $search,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function show(int $id)
    {
        Gate::authorize(DataRetentionRun::getPermissions()['archive-view']);

        $transaction = ArchiveTransaction::with(['details.item', 'sender', 'receiver'])->findOrFail($id);

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

        return view('archive.transactions.show', [
            'transaction' => $transaction,
            'config' => [
                'sender_label' => $getLabel('sender'),
                'receiver_label' => $getLabel('receiver'),
                'type_slug' => $typeSlug,
            ],
            'typeBadges' => $this->typeBadges(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function typeBadges(): array
    {
        return [
            Transaction::TYPE_BUY => ['Buy', 'border-emerald-200 bg-emerald-100 text-emerald-700'],
            Transaction::TYPE_SELL => ['Sell', 'border-blue-200 bg-blue-100 text-blue-700'],
            Transaction::TYPE_MOVE => ['Move', 'border-amber-200 bg-amber-100 text-amber-700'],
            Transaction::TYPE_TRANSFER => ['Transfer', 'border-cyan-200 bg-cyan-100 text-cyan-700'],
            Transaction::TYPE_CASH_OUT => ['Cash Out', 'border-rose-200 bg-rose-100 text-rose-700'],
            Transaction::TYPE_USE => ['Use', 'border-yellow-200 bg-yellow-100 text-yellow-700'],
            Transaction::TYPE_CASH_IN => ['Cash In', 'border-purple-200 bg-purple-100 text-purple-700'],
            Transaction::TYPE_ADJUST => ['Adjust', 'border-indigo-200 bg-indigo-100 text-indigo-700'],
            Transaction::TYPE_RETURN => ['Return', 'border-rose-200 bg-rose-100 text-rose-700'],
            Transaction::TYPE_PRODUCTION => ['Production', 'border-slate-200 bg-slate-100 text-slate-700'],
            Transaction::TYPE_RETURN_SUPPLIER => ['Ret. Supplier', 'border-orange-200 bg-orange-100 text-orange-700'],
            Transaction::TYPE_DEPRECIATION => ['Depreciation', 'border-zinc-200 bg-zinc-100 text-zinc-700'],
        ];
    }
}
