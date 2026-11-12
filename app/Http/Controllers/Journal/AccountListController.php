<?php

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Operation;
use App\Models\Transaction;
use App\Services\TransactionListExportService;
use Illuminate\Database\Eloquent\Builder;
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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
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

        $account = $account_list->load('operation', 'stat');
        $query = $this->ledgerTransactionsQuery($request, $account);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($query->paginate((int) $request->input('size', 50))->withQueryString());
        }

        $user = $request->user();

        return view('journals.account-list.ledger', [
            'account' => $account,
            'transactions' => $query->paginate(50)->withQueryString(),
            'transactionTypes' => Transaction::getTypes(),
            'filters' => $request->only(['from', 'to', 'type', 'order_date']),
            'can' => [
                'edit_transaction' => $user?->can(Transaction::getPermissions()['edit']) ?? false,
                'delete_transaction' => $user?->can(Transaction::getPermissions()['delete']) ?? false,
                'bank_hidden_balance' => ! ($user?->is_superadmin ?? false) && ($user?->can('addrbook-bank-account-hidden-balance') ?? false),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function ledgerExport(Request $request, Addrbook $account_list, TransactionListExportService $exportService)
    {
        Gate::authorize(Operation::getPermissions()['account-list']);

        $account = $account_list->load('operation');
        $user = $request->user();
        $hideBank = ! ($user?->is_superadmin ?? false) && ($user?->can('addrbook-bank-account-hidden-balance') ?? false);

        $slug = str($account->name)->slug()->limit(40, '');

        return $exportService->downloadCsv(
            $this->ledgerTransactionsQuery($request, $account)->cursor(),
            sprintf('ledger-%s-%s.csv', $slug, now()->format('Y-m-d-His')),
            $hideBank,
        );
    }

    protected function ledgerTransactionsQuery(Request $request, Addrbook $account): Builder
    {
        $accountId = $account->id;

        $query = Transaction::query()
            ->where(fn ($q) => $q
                ->where('sender_id', $accountId)
                ->orWhere('receiver_id', $accountId))
            ->visibleToUser($request->user())
            ->with(['sender', 'receiver', 'user'])
            ->when($request->query('from'), fn ($q) => $q->whereDate('date', '>=', $request->query('from')))
            ->when($request->query('to'), fn ($q) => $q->whereDate('date', '<=', $request->query('to')))
            ->when($request->query('type'), fn ($q) => $q->where('type', $request->query('type')));

        if ($request->query('order_date', 'date') === 'created_at') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        return $query;
    }
}
