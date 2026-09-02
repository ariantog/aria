<?php

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Operation;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountListController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Operation::getPermissions()['account-list']);

        $query = Addrbook::account()->with('operation');

        if ($search = $request->search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($operationId = $request->operation_id) {
            $query->where('parent_id', $operationId);
        }

        return view('journals.account-list.index', [
            'accounts' => $query->orderBy('name')->orderBy('id')->paginate(50)->withQueryString(),
            'operations' => Operation::all(['id', 'name']),
            'filters' => $request->only(['search', 'operation_id']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Operation::getPermissions()['account-create']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ledger_hint' => 'nullable|string|max:500',
            'operation_id' => 'required|exists:operations,id',
        ]);

        DB::transaction(function () use ($validated) {
            $account = Addrbook::create([
                ...$validated,
                'type' => Addrbook::TYPE_ACCOUNT,
            ]);

            AddrbookStat::create([
                'customer_id' => $account->id,
                'balance' => 0,
            ]);
        });

        return redirect()->back()->with('success', 'Account created successfully.');
    }

    public function update(Request $request, Addrbook $account_list)
    {
        Gate::authorize(Operation::getPermissions()['account-edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ledger_hint' => 'nullable|string|max:500',
            'operation_id' => 'required|exists:operations,id',
        ]);

        $account_list->update($validated);

        return redirect()->back()->with('success', 'Account updated successfully.');
    }

    public function destroy(Addrbook $account_list)
    {
        Gate::authorize(Operation::getPermissions()['account-delete']);

        $account_list->delete();

        return redirect()->back()->with('success', 'Account deleted successfully.');
    }

    public function ledger(Request $request, Addrbook $account_list)
    {
        Gate::authorize(Operation::getPermissions()['account-list']);

        $accountId = $account_list->id;

        $from = $request->from;
        $to = $request->to;

        $query = Transaction::orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        } else {
            $fromDate = Carbon::now()->subYear(1)->toDateString();
            $toDate = Carbon::now()->toDateString();
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        $query->where(function ($q) use ($accountId) {
            $q->where('sender_id', $accountId)->orWhere('receiver_id', $accountId);
        });

        $transactions = $query->paginate(50)->withQueryString();

        return view('journals.account-list.ledger', [
            'account' => $account_list->load('operation', 'stat'),
            'transactions' => $transactions,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
