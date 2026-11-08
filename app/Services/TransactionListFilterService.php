<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TransactionListFilterService
{
    public function filteredTransactionsQuery(Request $request, User $user): Builder
    {
        return Transaction::with(['sender', 'receiver'])
            ->visibleToUser($user)
            ->when($request->query('invoice'), fn ($q, $v) => $q->where('invoice', 'like', "%{$v}%"))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->filled('total'), fn ($q) => $q->where('total', '=', $request->input('total')))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 100);

        return in_array($perPage, [100, 200, 300], true) ? $perPage : 100;
    }
}
