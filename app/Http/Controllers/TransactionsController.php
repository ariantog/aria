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
use App\Models\DeletedTransaction;
use App\Models\DeletedTransactionDetail;
use App\Models\Transaction;
use App\Services\BookClosingService;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use App\Services\TransactionInvoiceService;
use App\Services\TransactionListExportService;
use App\Services\TransactionReturnDraftService;
use App\Services\TransactionService;
use App\Services\UserPreferenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class TransactionsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Transaction::getPermissions()['view']);
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        $perPage = $this->resolvePerPage($request);
        $transactions = $this->filteredTransactionsQuery($request);
        if (in_array($sort, ['date', 'invoice', 'type', 'total', 'total_items'], true)) {
            $transactions->orderBy($sort, $direction)->orderBy('id', 'desc');
        } else {
            $transactions->orderBy('date', 'desc')->orderBy('id', 'desc');
        }
        $filters = $request->only(['from', 'to', 'sort', 'direction', 'type', 'invoice', 'min_total', 'max_total', 'per_page']);
        $can = $this->transactionPermissions();
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($transactions->paginate($perPage)->withQueryString());
        }

        $rows = $transactions->paginate($perPage)->withQueryString();

        return view('transactions.index', compact('rows', 'filters', 'can', 'sort', 'direction', 'perPage'));
    }

    public function export(Request $request, TransactionListExportService $exportService)
    {
        Gate::authorize(Transaction::getPermissions()['view']);
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        $perPage = $this->resolvePerPage($request);
        $transactions = $this->filteredTransactionsQuery($request);
        if (in_array($sort, ['date', 'invoice', 'type', 'total', 'total_items'], true)) {
            $transactions->orderBy($sort, $direction)->orderBy('id', 'desc');
        } else {
            $transactions->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        $page = max(1, (int) $request->input('page', 1));
        $rows = $transactions->paginate($perPage, ['*'], 'page', $page);
        $hideBank = ! Auth::user()->is_superadmin && Auth::user()->can('addrbook-bank-account-hidden-balance');

        return $exportService->download($rows, $hideBank);
    }

    public function create(string $type, Request $request, BookClosingService $bookClosingService, TransactionReturnDraftService $draftService, UserPreferenceService $userPreferences)
    {
        Transaction::authorizeTypeAccess($type);
        $config = config("transaction_rules.{$type}");
        if (! $config) {
            abort(404, "Transaction type '{$type}' not supported.");
        }
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
            'type' => $type,
            'config' => $config,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'prefill' => $this->resolveCreatePrefill($type, $request, $draftService, $userPreferences),
        ]);
    }

    /**
     * Resolve a scanned barcode (= item id) for the line-item rows.
     * Covers both regular items and asset lancar.
     */
    public function itemById(string $type, Request $request)
    {
        Transaction::authorizeTypeAccess($type);

        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $item = \App\Models\Item::with('warehouseItems')->find($validated['id']);
        if (! $item) {
            return response()->json(['item' => null]);
        }

        return response()->json([
            'item' => [
                'id' => $item->id,
                'code' => $item->getItemCode(),
                'name' => $item->name ?: $item->getItemName(),
                'type' => $item->type->value,
                'price' => (float) $item->price,
                'cost' => (float) $item->cost,
                'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ],
        ]);
    }

    public function store(StoreItemTransactionRequest $request, CreateItemTransaction $action, BookClosingService $bookClosingService)
    {
        $this->authorizeTransactionType($request->input('type'));
        $bookClosingService->validateDate($request->validated('date'));
        $transaction = $action->execute($request);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction created.');
    }

    public function cashIn(BookClosingService $bookClosingService, UserPreferenceService $userPreferences)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);

        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'type' => 'in',
            'defaultAccount' => $userPreferences->defaultCashAccount(Auth::user(), true),
        ]);
    }

    public function storeCashIn(StoreCashTransactionRequest $request, CreateCashTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: true);

        return redirect()->route('transactions.show', end($ids))->with('success', 'Cash In records created.');
    }

    public function cashOut(BookClosingService $bookClosingService, UserPreferenceService $userPreferences)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);

        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'type' => 'out',
            'defaultAccount' => $userPreferences->defaultCashAccount(Auth::user(), false),
        ]);
    }

    public function storeCashOut(StoreCashTransactionRequest $request, CreateCashTransaction $action, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: false);

        return redirect()->route('transactions.show', end($ids))->with('success', 'Cash Out records created.');
    }

    public function transfer(BookClosingService $bookClosingService, UserPreferenceService $userPreferences)
    {
        Gate::authorize(Transaction::getPermissions()['type-transfer']);

        return view('transactions.transfer', [
            'bankList' => \App\Models\Addrbook::query()
                ->whereIn('type', \App\Models\Addrbook::transferAccountTypes())
                ->orderBy('name')
                ->get(),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'defaultAccounts' => $userPreferences->defaultTransferAccounts(Auth::user()),
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

    public function show(Transaction $transaction, JubelioTransactionSyncPresenter $jubelioSyncPresenter)
    {
        Gate::authorize(Transaction::getPermissions()['show']);
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessTransaction(Auth::user(), $transaction),
            403
        );
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
        $jubelioSync = $jubelioSyncPresenter->applyToTransaction($transaction);
        $jubelioSync['show_ui'] = $jubelioSync['can_sync']
            && $jubelioSync['sync_cek']
            && Transaction::userCanJubelioTransactionSync(Auth::user());
        $invoiceService = app(TransactionInvoiceService::class);
        $canDraftReturn = $this->canDraftReturn($transaction);

        return view('transactions.show', [
            'transaction' => $transaction,
            'jubelioSync' => $jubelioSync,
            'config' => ['sender_label' => $getLabel('sender'), 'receiver_label' => $getLabel('receiver'), 'type_slug' => $typeSlug],
            'can' => [
                'delete_transaction' => Auth::user()->can(Transaction::getPermissions()['delete']),
                'edit_transaction' => Auth::user()->can(Transaction::getPermissions()['edit']),
                'bank_hidden_balance' => ! Auth::user()->is_superadmin && Auth::user()->can('addrbook-bank-account-hidden-balance'),
                'return_draft' => $canDraftReturn,
                'jubelio_transaction_sync' => Transaction::userCanJubelioTransactionSync(Auth::user()),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
            'hasInvoicePdf' => $invoiceService->invoicePdfExists($transaction),
            'invoicePdfUrl' => $invoiceService->invoicePdfUrl($transaction),
        ]);
    }

    /**
     * Legacy entry point — redirects to the create form with ?from= (no session storage).
     * Kept so older cached transaction detail pages still work after deploy.
     */
    public function draftReturn(Transaction $transaction, TransactionReturnDraftService $draftService)
    {
        $this->authorizeTransactionView($transaction);
        $targetType = $draftService->targetTypeSlug($transaction);
        abort_unless($targetType, 422, 'This transaction type cannot be returned.');
        Transaction::authorizeTypeAccess($targetType);

        return redirect()->route('transactions.create', [
            'type' => $targetType,
            'from' => $transaction->id,
        ]);
    }

    public function showPdf(Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        $this->authorizeTransactionView($transaction);
        abort_unless($invoiceService->invoicePdfExists($transaction), 404);

        $filePath = $invoiceService->invoiceDiskPath($invoiceService->invoiceFileName($transaction));

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoiceService->invoiceFileName($transaction).'"',
        ]);
    }

    public function storePdf(Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        $this->authorizeTransactionView($transaction);
        $existed = $invoiceService->invoicePdfExists($transaction);
        $invoiceService->createInvoicePdf($transaction, regenerate: true);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', $existed ? 'Invoice PDF regenerated.' : 'Invoice PDF saved.');
    }

    public function receipt(Transaction $transaction, \App\Services\InvoiceBrandingService $brandingService)
    {
        $this->authorizeTransactionView($transaction);
        $transaction->load(['details.item.group', 'sender', 'receiver']);
        $branding = $brandingService->forTransaction($transaction);

        return view('transactions.receipt', compact('transaction', 'branding'));
    }

    public function printInvoice(Transaction $transaction, \App\Services\InvoiceBrandingService $brandingService)
    {
        $this->authorizeTransactionView($transaction);
        $transaction->load(['details.item.group', 'sender', 'receiver']);
        $typeLabel = $transaction->getTypeLabel();
        $branding = $brandingService->forTransaction($transaction);

        return view('transactions.print', compact('transaction', 'typeLabel', 'branding'));
    }

    public function sendWhatsapp(Request $request, Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        $this->authorizeTransactionView($transaction);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{8,15}$/'],
        ]);

        $fileUrl = $invoiceService->ensureInvoicePdf($transaction);
        $message = urlencode("Terimakasih telah belanja di CoreNation! Berikut invoice anda:\n\n{$fileUrl}");
        $phone = preg_replace('/\D/', '', $validated['phone']);
        $waLink = 'https://wa.me/'.$phone.'?text='.$message;

        return redirect()->away($waLink);
    }

    public function batchParse(Request $request)
    {
        $request->validate(['csv_file' => 'required|mimes:csv,txt|max:5120', 'warehouse_id' => 'nullable|integer']);
        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();
        $array = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $first = true;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (count($data) >= 3) {
                    if ($first && ! is_numeric(trim($data[1]))) {
                        $first = false;

                        continue;
                    }
                    $first = false;
                    $array[] = ['code' => trim($data[0]), 'qty' => trim($data[1]), 'price' => trim($data[2])];
                }
            }
            fclose($handle);
        }
        if (empty($array)) {
            return response()->json(['error' => 'Failed to parse CSV.'], 422);
        }
        $codes = collect($array)->pluck('code')->unique()->all();
        $whid = $request->warehouse_id;
        $itemsBySku = \App\Models\Item::findManyBySkus($codes);
        $itemIds = $itemsBySku->pluck('id')->unique()->values();
        $itemsQuery = \App\Models\Item::query()->whereIn('id', $itemIds);
        if ($whid) {
            $itemsQuery->with(['warehouseItems' => fn ($q) => $q->where('warehouse_id', $whid)]);
        }
        $items = $itemsQuery->get()->keyBy('id');
        $dataList = [];
        foreach ($array as $row) {
            $resolved = $itemsBySku->get(strtoupper($row['code']));
            $item = $resolved ? ($items[$resolved->id] ?? $resolved) : null;
            if ($item) {
                $warehouseItem = [];
                $whQty = 0;
                if ($whid && $item->relationLoaded('warehouseItems')) {
                    $warehouseItem = $item->warehouseItems->map(fn ($wi) => [
                        'warehouse_id' => (string) $wi->warehouse_id,
                        'quantity' => (float) $wi->quantity,
                    ])->values()->all();
                    $whQty = $warehouseItem[0]['quantity'] ?? 0;
                }
                $dataList[] = [
                    'id' => (string) $item->id,
                    'item_id' => (string) $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'quantity' => (float) $row['qty'],
                    'warehouse_stock' => $whQty,
                    'warehouse_item' => $warehouseItem,
                    'warehouse_id' => $whid,
                    'price' => (float) $row['price'],
                    'discount' => 0,
                    'subtotal' => (float) $row['qty'] * (float) $row['price'],
                    'note' => '',
                ];
            }
        }

        return response()->json(['data' => $dataList, 'totalQty' => collect($dataList)->sum('quantity'), 'totalPrice' => collect($dataList)->sum('subtotal')]);
    }

    public function destroy(Transaction $transaction, TransactionService $service)
    {
        Gate::authorize(Transaction::getPermissions()['delete']);
        if ($transaction->isFromJubelio()) {
            return back()->with('error', 'Jubelio-synced transactions cannot be deleted.');
        }

        $transaction->load('details');

        DB::transaction(function () use ($transaction, $service) {
            $deletedColumns = array_flip(Schema::getColumnListing((new DeletedTransaction)->getTable()));
            $transactionData = array_intersect_key($transaction->getAttributes(), $deletedColumns);
            $transactionData['deleted_at'] = now();

            $deletedDetailColumns = array_flip(Schema::getColumnListing((new DeletedTransactionDetail)->getTable()));
            $detailRows = [];
            foreach ($transaction->details as $detail) {
                $detailData = array_intersect_key($detail->getAttributes(), $deletedDetailColumns);
                $detailData['deleted_at'] = now();
                $detailRows[] = $detailData;
            }

            $service->revertTransaction($transaction);

            DeletedTransaction::create($transactionData);
            foreach ($detailRows as $detailData) {
                DeletedTransactionDetail::create($detailData);
            }

            $transaction->details()->delete();
            $transaction->delete();
        });

        return redirect()->route('transactions.index')->with('success', 'Transaction moved to deleted.');
    }

    private function authorizeTransactionType(string $type): void
    {
        Transaction::authorizeTypeAccess($type);
    }

    private function authorizeTransactionView(Transaction $transaction): void
    {
        Gate::authorize(Transaction::getPermissions()['show']);
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessTransaction(Auth::user(), $transaction),
            403
        );
    }

    private function canDraftReturn(Transaction $transaction): bool
    {
        $user = Auth::user();
        $perms = Transaction::getPermissions();
        $type = (int) $transaction->type;

        return match ($type) {
            Transaction::TYPE_SELL => $user->can($perms['type-return']),
            Transaction::TYPE_BUY => $user->can($perms['type-return-supplier']),
            Transaction::TYPE_MOVE => $user->can($perms['type-move']),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCreatePrefill(string $type, Request $request, TransactionReturnDraftService $draftService, UserPreferenceService $userPreferences): ?array
    {
        if ($request->filled('from')) {
            return $this->buildReturnPrefillFromSource((int) $request->query('from'), $type, $draftService);
        }

        if ($type === 'move') {
            $sessionPrefill = session()->pull('transaction_move_prefill');
            if ($sessionPrefill) {
                return $sessionPrefill;
            }
        }

        return $userPreferences->transactionPrefill(Auth::user(), $type);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReturnPrefillFromSource(int $sourceId, string $type, TransactionReturnDraftService $draftService): array
    {
        $transaction = Transaction::find($sourceId);
        abort_unless($transaction, 404);

        $this->authorizeTransactionView($transaction);

        $targetType = $draftService->targetTypeSlug($transaction);
        abort_unless($targetType === $type, 422, 'This transaction cannot be used for this form.');

        return $draftService->buildPrefill($transaction);
    }

    private function filteredTransactionsQuery(Request $request): Builder
    {
        return Transaction::with(['sender', 'receiver'])
            ->visibleToUser(Auth::user())
            ->when($request->invoice, fn ($q, $v) => $q->where('invoice', 'like', "%{$v}%"))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->min_total, fn ($q, $v) => $q->where('total', '>=', $v))
            ->when($request->max_total, fn ($q, $v) => $q->where('total', '<=', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 100);

        return in_array($perPage, [100, 200, 300], true) ? $perPage : 100;
    }

    private function transactionPermissions(): array
    {
        $user = Auth::user();
        $perms = Transaction::getPermissions();

        return [
            'create_transaction' => $user->can($perms['create']), 'delete_transaction' => $user->can($perms['delete']),
            'bank_hidden_balance' => ! $user->is_superadmin && $user->can('addrbook-bank-account-hidden-balance'),
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
}
