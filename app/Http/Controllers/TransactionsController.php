<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateAdjustTransaction;
use App\Actions\Transactions\CreateCashTransaction;
use App\Actions\Transactions\CreateItemTransaction;
use App\Actions\Transactions\CreateTransferTransaction;
use App\Http\Requests\StoreAdjustRequest;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Http\Requests\StoreItemTransactionRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Services\BookClosingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransactionsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Transaction::getPermissions()['view']);
        $transactions = Transaction::with(['sender', 'receiver'])
            ->when($request->invoice_number, fn ($q, $v) => $q->where('invoice_number', 'like', "%{$v}%"))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->min_total, fn ($q, $v) => $q->where('grand_total', '>=', $v))
            ->when($request->max_total, fn ($q, $v) => $q->where('grand_total', '<=', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v));
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        if (in_array($sort, ['date', 'invoice_number', 'type', 'grand_total'], true)) {
            $transactions->orderBy($sort, $direction)->orderBy('id', 'desc');
        } else { $transactions->orderBy('date', 'desc')->orderBy('id', 'desc'); }
        $filters = $request->only(['from', 'to', 'sort', 'direction', 'type', 'invoice_number', 'min_total', 'max_total']);
        $can     = $this->transactionPermissions();
        $rows    = $transactions->paginate(50)->withQueryString();

        return view('transactions.index', compact('rows', 'filters', 'can', 'sort', 'direction'));
    }

    public function create(string $type, BookClosingService $bookClosingService)
    {
        $permissionKey = 'type_'.str_replace('-', '_', $type);
        $permissions = Transaction::getPermissions();
        Gate::authorize($permissions[$permissionKey] ?? $permissions['create']);
        $config = config("transaction_rules.{$type}");
        if (! $config) abort(404, "Transaction type '{$type}' not supported.");
        $config['sender_route'] = route('transactions.lookup', ['type' => $type, 'role' => 'sender', 'addrbook_type' => $config['sender_type'] ?? null]);
        $config['receiver_route'] = route('transactions.lookup', ['type' => $type, 'role' => 'receiver', 'addrbook_type' => $config['receiver_type'] ?? null]);
        $getLabel = function ($role) use ($config) {
            if (isset($config[$role.'_type'])) {
                $types = collect(\App\Models\Addrbook::getTypes());
                $typeIds = (array) $config[$role.'_type'];
                $names = $types->whereIn('id', $typeIds)->pluck('name')->toArray();
                return ! empty($names) ? implode(' / ', $names) : 'Contact';
            }
            return 'Contact';
        };
        $config['sender_label'] = $getLabel('sender');
        $config['receiver_label'] = $getLabel('receiver');
        return view('transactions.create', [
            'type'     => $type,
            'config'   => $config,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function store(StoreItemTransactionRequest $request, CreateItemTransaction $action, BookClosingService $bookClosingService)
    {
        $this->authorizeTransactionType($request->input('type'));
        $bookClosingService->validateDate($request->validated('date'));
        $transaction = $action->execute($request);
        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction created.');
    }

    public function cashIn(BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);
        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Enums\AddrbookType::Bank->value)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(), 'type' => 'in',
        ]);
    }

    public function storeCashIn(StoreCashTransactionRequest $request, CreateCashTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: true);
        return redirect()->route('transactions.show', end($ids))->with('success', 'Cash In records created.');
    }

    public function cashOut(BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);
        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Enums\AddrbookType::Bank->value)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(), 'type' => 'out',
        ]);
    }

    public function storeCashOut(StoreCashTransactionRequest $request, CreateCashTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: false);
        return redirect()->route('transactions.show', end($ids))->with('success', 'Cash Out records created.');
    }

    public function transfer(BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-transfer']);
        return view('transactions.transfer', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Enums\AddrbookType::Bank->value)->orderBy('name')->get(),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function storeTransfer(StoreTransferRequest $request, CreateTransferTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-transfer']);
        $bookClosingService->validateDate($request->validated('date'));
        $transaction = $action->execute($request);
        return redirect()->route('transactions.show', $transaction)->with('success', 'Transfer created.');
    }

    public function adjust(BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-adjust']);
        return view('transactions.adjust', ['min_date' => $bookClosingService->getMinAllowedDate()->toDateString()]);
    }

    public function storeAdjust(StoreAdjustRequest $request, CreateAdjustTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-adjust']);
        $bookClosingService->validateDate($request->validated('date'));
        $transaction = $action->execute($request);
        return redirect()->route('transactions.show', $transaction)->with('success', 'Adjustment created.');
    }

    public function show(Transaction $transaction)
    {
        Gate::authorize(Transaction::getPermissions()['show']);
        $transaction->load(['details.item', 'sender', 'receiver', 'user', 'submitByA', 'submitByB']);
        $typeSlug = $this->resolveTypeSlug($transaction);
        $config = config("transaction_rules.{$typeSlug}");
        $getLabel = function ($role) use ($config) {
            if (isset($config[$role.'_type'])) {
                $types = collect(\App\Models\Addrbook::getTypes());
                $type = $types->firstWhere('id', $config[$role.'_type']);
                return $type ? $type['name'] : 'Contact';
            }
            return 'Contact';
        };
        $this->hydrateJubelioSyncData($transaction);
        return inertia('Transactions/Show', [
            'transaction' => $transaction,
            'config' => ['sender_label' => $getLabel('sender'), 'receiver_label' => $getLabel('receiver'), 'type_slug' => $typeSlug],
            'can' => ['delete_transaction' => Auth::user()->can(Transaction::getPermissions()['delete']), 'edit_transaction' => Auth::user()->can(Transaction::getPermissions()['edit']), 'bank_hidden_balance' => Auth::user()->can('addrbook-bank-account-hidden-balance')],
        ]);
    }

    public function batchParse(Request $request)
    {
        $request->validate(['csv_file' => 'required|mimes:csv,txt|max:5120', 'warehouse_id' => 'nullable|integer']);
        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();
        $array = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle); rewind($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $first = true;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (count($data) >= 3) {
                    if ($first && ! is_numeric(trim($data[1]))) { $first = false; continue; }
                    $first = false;
                    $array[] = ['code' => trim($data[0]), 'qty' => trim($data[1]), 'price' => trim($data[2])];
                }
            }
            fclose($handle);
        }
        if (empty($array)) return response()->json(['error' => 'Failed to parse CSV.'], 422);
        $codes = collect($array)->pluck('code')->unique();
        $whid = $request->warehouse_id;
        $itemsQuery = \App\Models\Item::whereIn('code', $codes);
        if ($whid) $itemsQuery->with(['warehouseItems' => fn ($q) => $q->where('warehouse_id', $whid)]);
        $items = $itemsQuery->get()->keyBy('code');
        $dataList = [];
        foreach ($array as $row) {
            $item = $items[$row['code']] ?? null;
            if ($item) {
                $whQty = 0;
                if ($whid && $item->relationLoaded('warehouseItems')) { $whItem = $item->warehouseItems->first(); $whQty = $whItem ? (float) $whItem->quantity : 0; }
                $dataList[] = ['id' => (string) $item->id, 'item_id' => (string) $item->id, 'code' => $item->code, 'name' => $item->name, 'quantity' => (float) $row['qty'], 'warehouse_stock' => $whQty, 'warehouse_id' => $whid, 'price' => (float) $row['price'], 'discount' => 0, 'subtotal' => (float) $row['qty'] * (float) $row['price'], 'note' => ''];
            }
        }
        return response()->json(['data' => $dataList, 'totalQty' => collect($dataList)->sum('quantity'), 'totalPrice' => collect($dataList)->sum('subtotal')]);
    }

    public function destroy(Transaction $transaction)
    {
        Gate::authorize(Transaction::getPermissions()['delete']);
        if ($transaction->isFromJubelio()) return back()->with('error', 'Jubelio-synced transactions cannot be deleted.');
        $transaction->delete();
        return redirect()->route('transactions.index')->with('success', 'Transaction deleted.');
    }

    private function authorizeTransactionType(string $type): void
    {
        $permissionKey = 'type_'.str_replace('-', '_', $type);
        $permissions = Transaction::getPermissions();
        Gate::authorize($permissions[$permissionKey] ?? $permissions['create']);
    }

    private function transactionPermissions(): array
    {
        $user = Auth::user(); $perms = Transaction::getPermissions();
        return [
            'create_transaction' => $user->can($perms['create']), 'delete_transaction' => $user->can($perms['delete']),
            'type_buy' => $user->can($perms['type-buy']), 'type_sell' => $user->can($perms['type-sell']),
            'type_move' => $user->can($perms['type-move']), 'cash_in' => $user->can($perms['type-cash-in']),
            'cash_out' => $user->can($perms['type-cash-out']), 'transfer' => $user->can($perms['type-transfer']),
            'adjust' => $user->can($perms['type-adjust']), 'return' => $user->can($perms['type-return']),
            'return_supplier' => $user->can($perms['type-return-supplier']),
        ];
    }

    private function resolveTypeSlug(Transaction $transaction): string
    {
        $typeKey = collect(config('transaction_rules'))->firstWhere('id', $transaction->type);
        return $typeKey ? array_search($typeKey, config('transaction_rules')) : 'adjust';
    }

    private function hydrateJubelioSyncData(Transaction $transaction): void
    {
        $isManual = $transaction->submit_type === Transaction::SUBMIT_TYPE_MANUAL;
        $syncRelevantA = in_array($transaction->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER, Transaction::TYPE_MOVE]);
        $syncRelevantB = in_array($transaction->type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN, Transaction::TYPE_MOVE]);
        $syncedWarehouseIds = Jubeliosync::pluck('warehouse_id')->toArray();
        $jubSyncA = Jubeliosync::where('warehouse_id', $transaction->sender_id)->first();
        $jubSyncB = Jubeliosync::where('warehouse_id', $transaction->receiver_id)->first();
        $transaction->jubelio_a = ($isManual && $jubSyncA && $syncRelevantA) ? $jubSyncA->jubelio_location_name : null;
        $transaction->jubelio_b = ($isManual && $jubSyncB && $syncRelevantB) ? $jubSyncB->jubelio_location_name : null;
        $sync_cek = null;
        if ($isManual) {
            if (in_array($transaction->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER])) $sync_cek = in_array($transaction->sender_id, $syncedWarehouseIds) ? 'S' : null;
            elseif (in_array($transaction->type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN])) $sync_cek = in_array($transaction->receiver_id, $syncedWarehouseIds) ? 'R' : null;
            elseif ($transaction->type == Transaction::TYPE_MOVE) {
                $sS = in_array($transaction->sender_id, $syncedWarehouseIds);
                $rS = in_array($transaction->receiver_id, $syncedWarehouseIds);
                $sync_cek = match (true) { $sS && $rS => 'B', $sS => 'S', $rS => 'R', default => null };
            }
        }
        $transaction->sync_cek = $sync_cek;
        $transaction->a_synced = $isManual && (bool) $transaction->a_submit_by;
        $transaction->b_synced = $isManual && (bool) $transaction->b_submit_by;
        $transaction->is_from_jubelio = $transaction->submit_type === Transaction::SUBMIT_TYPE_JUBELIO;
    }
}
