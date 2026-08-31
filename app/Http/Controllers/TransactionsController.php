<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateAdjustTransaction;
use App\Actions\Transactions\CreateCashInFromSell;
use App\Actions\Transactions\CreateCashTransaction;
use App\Actions\Transactions\CreateItemTransaction;
use App\Actions\Transactions\CreateTransferTransaction;
use App\Http\Requests\StoreAdjustRequest;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Http\Requests\StoreItemTransactionRequest;
use App\Http\Requests\StoreSellCashInRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\DeletedTransaction;
use App\Models\DeletedTransactionDetail;
use App\Models\Transaction;
use App\Services\BookClosingService;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use App\Services\Reporting\ReportingSummaryRecorder;
use App\Services\StandaloneInvoiceSettlement;
use App\Services\TransactionInvoiceService;
use App\Services\TransactionListExportService;
use App\Services\TransactionReturnDraftService;
use App\Services\TransactionService;
use App\Services\UserPreferenceService;
use App\Support\PpnAmounts;
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

    public function create(string $type, Request $request, BookClosingService $bookClosingService, TransactionReturnDraftService $draftService, UserPreferenceService $userPreferences, JubelioTransactionSyncPresenter $jubelioSyncPresenter)
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
            'jubelio_sync' => $jubelioSyncPresenter->createFormSyncConfig(),
            'sellCashIn' => $type === 'sell' ? $this->sellCashInFormData(Auth::user()) : null,
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
            'item' => $this->itemLookupPayload($item),
        ]);
    }

    /**
     * Resolve a typed SKU for line-item rows (canonical code, legacy_code, then name).
     */
    public function itemByCode(string $type, Request $request)
    {
        Transaction::authorizeTypeAccess($type);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $item = \App\Models\Item::findBySkuOrName($validated['code']);
        if ($item) {
            $item->loadMissing('warehouseItems');
        }

        return response()->json([
            'item' => $item ? $this->itemLookupPayload($item) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemLookupPayload(\App\Models\Item $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->getItemCode(),
            'name' => $item->name ?: $item->getItemName(),
            'type' => $item->type->value,
            'price' => (float) $item->price,
            'cost' => (float) $item->cost,
            'jubelio_item_id' => (int) ($item->jubelio_item_id ?? 0),
            'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                'warehouse_id' => (string) $wi->warehouse_id,
                'quantity' => (float) $wi->quantity,
            ])->values()->all(),
        ];
    }

    public function store(StoreItemTransactionRequest $request, CreateItemTransaction $action, BookClosingService $bookClosingService)
    {
        $this->authorizeTransactionType($request->input('type'));
        $bookClosingService->validateDate($request->validated('date'));
        if ($request->wantsCashIn()) {
            Gate::authorize(Transaction::getPermissions()['type-cash-in']);
            $bookClosingService->validateDate(
                $request->validated('cash_in_date') ?: now()->toDateString(),
                'cash_in_date'
            );
        }
        $transaction = $action->execute($request);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction created.');
    }

    public function cashIn(BookClosingService $bookClosingService, UserPreferenceService $userPreferences)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);

        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'pph_rate' => (float) config('reporting.pph_withholding_rate', 10),
            'pkpBankIds' => \App\Models\ReportingEntity::activePkpBankIds(),
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

    public function storeSellCashIn(
        StoreSellCashInRequest $request,
        Transaction $transaction,
        CreateCashInFromSell $action,
        BookClosingService $bookClosingService
    ) {
        $this->authorizeTransactionView($transaction);
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);
        abort_unless((int) $transaction->type === Transaction::TYPE_SELL, 422, 'Cash in can only be created from a sell.');
        abort_unless((int) $transaction->status === Transaction::STATUS_COMPLETED, 422, 'Cash in can only be created from a completed sell.');

        $date = $request->validated('date') ?: now()->toDateString();
        $bookClosingService->validateDate($date);
        $action->execute($transaction, [
            'date' => $date,
            'account_id' => (int) $request->validated('account_id'),
            'amount' => (float) $request->validated('amount'),
        ]);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Cash In created.');
    }

    public function cashOut(BookClosingService $bookClosingService, UserPreferenceService $userPreferences)
    {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);

        return view('transactions.cash', [
            'bankList' => \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name')->get(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'pph_rate' => (float) config('reporting.pph_withholding_rate', 10),
            'pkpBankIds' => \App\Models\ReportingEntity::activePkpBankIds(),
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
        $transaction->load(['details.item.group', 'sender', 'receiver', 'user', 'submitByA', 'submitByB']);
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
        $jubelioSync['show_ui'] = $jubelioSyncPresenter->showSyncUi($jubelioSync, Auth::user());
        $invoiceService = app(TransactionInvoiceService::class);
        $canDraftReturn = $this->canDraftReturn($transaction);
        $invoiceSettlement = app(StandaloneInvoiceSettlement::class)->snapshotForTransaction($transaction);
        $sellCashIn = $this->sellCashInShowData($transaction, $invoiceSettlement);
        $cashBankId = match ((int) $transaction->type) {
            Transaction::TYPE_CASH_IN => (int) $transaction->receiver_id,
            Transaction::TYPE_CASH_OUT => (int) $transaction->sender_id,
            default => 0,
        };
        $cashReportingEntity = $cashBankId > 0
            ? \App\Models\ReportingEntity::findActiveForBank($cashBankId)
            : null;

        return view('transactions.show', [
            'transaction' => $transaction,
            'jubelioSync' => $jubelioSync,
            'config' => ['sender_label' => $getLabel('sender'), 'receiver_label' => $getLabel('receiver'), 'type_slug' => $typeSlug],
            'can' => [
                'delete_transaction' => Auth::user()->can(Transaction::getPermissions()['delete']),
                'edit_transaction' => Auth::user()->can(Transaction::getPermissions()['edit']),
                'edit_invoice' => Auth::user()->can(Transaction::getPermissions()['edit-invoice']),
                'bank_hidden_balance' => ! Auth::user()->is_superadmin && Auth::user()->can('addrbook-bank-account-hidden-balance'),
                'return_draft' => $canDraftReturn,
                'jubelio_transaction_sync' => Transaction::userCanJubelioTransactionSync(Auth::user()),
                'invoice_maker_view' => Auth::user()->can(\App\Models\StandaloneInvoice::getPermissions()['view']),
                'invoice_maker_edit' => Auth::user()->can(\App\Models\StandaloneInvoice::getPermissions()['edit']),
                'create_sell_cash_in' => (bool) ($sellCashIn['can_create'] ?? false),
            ],
            'invoiceSettlement' => $invoiceSettlement,
            'sellCashIn' => $sellCashIn,
            'flash' => [
                'success' => session('success'),
                'error' => session('errorMessage') ?? session('error'),
                'hint' => session('errorHint'),
            ],
            'hasInvoicePdf' => $invoiceService->invoicePdfExists($transaction),
            'invoicePdfUrl' => $invoiceService->invoicePdfUrl($transaction),
            'canEditPpn' => Auth::user()->can(Transaction::getPermissions()['edit'])
                && in_array((int) $transaction->type, [Transaction::TYPE_CASH_IN, Transaction::TYPE_CASH_OUT], true)
                && ($cashReportingEntity?->is_pkp ?? false),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'pph_rate' => (float) config('reporting.pph_withholding_rate', 10),
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

    public function storePdf(Request $request, Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        $this->authorizeTransactionView($transaction);
        $existed = $invoiceService->invoicePdfExists($transaction);
        $itemView = \App\Support\TransactionItemViewOptions::fromRequest($request);
        $invoiceService->createInvoicePdf($transaction, regenerate: true, itemView: $itemView);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', $existed ? 'Invoice PDF regenerated.' : 'Invoice PDF saved.');
    }

    public function receipt(Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        $this->authorizeTransactionView($transaction);
        $invoiceService->createReceiptPdf($transaction);
        $fileName = $invoiceService->receiptFileName($transaction);
        $filePath = $invoiceService->invoiceDiskPath($fileName);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    public function printInvoice(Request $request, Transaction $transaction, \App\Services\InvoiceBrandingService $brandingService)
    {
        $this->authorizeTransactionView($transaction);
        $transaction->load(['details.item.group', 'sender', 'receiver']);
        $typeLabel = $transaction->getTypeLabel();
        $branding = $brandingService->forTransaction($transaction);
        $itemView = \App\Support\TransactionItemViewOptions::fromRequest($request);

        return view('transactions.print', compact('transaction', 'typeLabel', 'branding', 'itemView'));
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
        $validated = $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:5120',
            'warehouse_id' => 'nullable|integer',
            'type' => 'nullable|string',
        ]);
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
                $csvPrice = (float) $row['price'];
                $itemPrice = (float) $item->price;
                $itemCost = (float) $item->cost;
                $type = $validated['type'] ?? '';
                $unitPrice = $type === 'move' ? $itemPrice : $csvPrice;
                $dataList[] = [
                    'id' => (string) $item->id,
                    'item_id' => (string) $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'jubelio_item_id' => (int) ($item->jubelio_item_id ?? 0),
                    'quantity' => (float) $row['qty'],
                    'warehouse_stock' => $whQty,
                    'warehouse_item' => $warehouseItem,
                    'warehouse_id' => $whid,
                    'price' => $unitPrice,
                    'cost' => $itemCost,
                    'item_price' => $itemPrice,
                    'csv_price' => $csvPrice,
                    'discount' => 0,
                    'subtotal' => (float) $row['qty'] * $unitPrice,
                    'note' => '',
                ];
            }
        }

        return response()->json(['data' => $dataList, 'totalQty' => collect($dataList)->sum('quantity'), 'totalPrice' => collect($dataList)->sum('subtotal')]);
    }

    public function updateNote(Request $request, Transaction $transaction)
    {
        Gate::authorize(Transaction::getPermissions()['edit']);
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessTransaction(Auth::user(), $transaction),
            403
        );

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = trim($validated['note'] ?? '');
        $transaction->update([
            'notes' => $note !== '' ? $note : null,
            'description' => $note,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'note' => $note !== '' ? $note : null,
                'display' => $note !== '' ? $note : '-',
            ]);
        }

        return back()->with('success', 'Transaction note updated.');
    }

    public function updateInvoice(Request $request, Transaction $transaction, TransactionInvoiceService $invoiceService)
    {
        Gate::authorize(Transaction::getPermissions()['edit-invoice']);
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessTransaction(Auth::user(), $transaction),
            403
        );

        $validated = $request->validate([
            'invoice' => ['nullable', 'string', 'max:50'],
        ]);

        $invoice = trim((string) ($validated['invoice'] ?? ''));
        if ($invoice === '') {
            $invoice = (string) $transaction->id;
        }

        $previous = (string) $transaction->invoice;
        $transaction->update(['invoice' => $invoice]);

        if ($previous !== $invoice) {
            $invoiceService->deleteInvoicePdf($transaction);
            app(StandaloneInvoiceSettlement::class)->reconcileByNumber($previous, Auth::user());
        }
        app(StandaloneInvoiceSettlement::class)->reconcileByNumber($invoice, Auth::user());

        if ($request->expectsJson()) {
            return response()->json([
                'invoice' => $invoice,
                'linked' => \App\Models\StandaloneInvoice::findByNumber($invoice) !== null,
            ]);
        }

        return back()->with('success', 'Invoice number updated.');
    }

    public function updatePpn(Request $request, Transaction $transaction, ReportingSummaryRecorder $recorder)
    {
        Gate::authorize(Transaction::getPermissions()['edit']);
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessTransaction(Auth::user(), $transaction),
            403
        );
        abort_unless(
            in_array((int) $transaction->type, [Transaction::TYPE_CASH_IN, Transaction::TYPE_CASH_OUT], true),
            422,
            'PPN can only be edited on cash in/out transactions.',
        );

        $bankId = (int) $transaction->type === Transaction::TYPE_CASH_IN
            ? (int) $transaction->receiver_id
            : (int) $transaction->sender_id;
        $entity = \App\Models\ReportingEntity::findActiveForBank($bankId);
        abort_unless($entity?->is_pkp, 422, 'This bank is not linked to a PKP reporting entity.');

        $validated = $request->validate([
            'record_ppn' => ['required', 'boolean'],
            'record_pph' => ['sometimes', 'boolean'],
            'ppn_dpp' => ['nullable', 'numeric', 'min:0'],
            'ppn' => ['nullable', 'numeric', 'min:0'],
            'pph' => ['nullable', 'numeric', 'min:0'],
        ]);

        $recordPpn = filter_var($validated['record_ppn'], FILTER_VALIDATE_BOOLEAN);
        $recordPph = filter_var($validated['record_pph'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ppn = 0.0;
        $ppnDpp = null;
        $pph = null;
        if ($recordPpn) {
            $submittedPpn = (float) ($validated['ppn'] ?? 0);
            $submittedDpp = isset($validated['ppn_dpp']) ? (float) $validated['ppn_dpp'] : null;
            $submittedPph = (float) ($validated['pph'] ?? 0);

            $hasManual = $submittedPpn >= 0.01 && $submittedDpp !== null && $submittedDpp >= 0.01;
            if ($recordPph) {
                $hasManual = $hasManual && $submittedPph >= 0.01;
            }

            if ($hasManual) {
                $ppn = $submittedPpn;
                $ppnDpp = $submittedDpp;
                $pph = $recordPph ? $submittedPph : null;
            } else {
                $amounts = PpnAmounts::fromPayment((float) $transaction->total, $recordPph);
                $ppn = $amounts['ppn'];
                $ppnDpp = $amounts['dpp'];
                $pph = $amounts['pph'] > 0 ? $amounts['pph'] : null;
            }
        }

        $previousPpn = (float) $transaction->ppn;
        $previousPpnDpp = $transaction->ppn_dpp !== null ? (float) $transaction->ppn_dpp : null;

        $transaction->update([
            'ppn' => $ppn,
            'ppn_dpp' => $ppnDpp,
            'pph' => $pph,
        ]);

        $recorder->adjustCashTransactionTax($transaction->fresh(), $previousPpn, $previousPpnDpp);

        if ($request->expectsJson()) {
            return response()->json([
                'ppn' => (float) $transaction->ppn,
                'ppn_dpp' => $transaction->ppn_dpp !== null ? (float) $transaction->ppn_dpp : null,
                'pph' => $transaction->pph !== null ? (float) $transaction->pph : null,
                'record_ppn' => (float) $transaction->ppn > 0,
                'record_pph' => (float) ($transaction->pph ?? 0) > 0,
                'display_ppn' => format_amount($transaction->ppn),
                'display_dpp' => $transaction->ppn_dpp !== null ? format_amount($transaction->ppn_dpp) : '-',
                'display_pph' => $transaction->pph !== null ? format_amount($transaction->pph) : '-',
            ]);
        }

        return back()->with('success', 'Transaction PPN updated.');
    }

    public function destroy(Transaction $transaction, TransactionService $service, BookClosingService $bookClosingService)
    {
        Gate::authorize(Transaction::getPermissions()['delete']);
        if ($transaction->isFromJubelio()) {
            return back()->with('error', 'Jubelio-synced transactions cannot be deleted.');
        }

        $transaction->load(['details', 'sender', 'receiver']);
        $sender = $transaction->sender;
        $receiver = $transaction->receiver;
        $invoiceNumber = (string) $transaction->invoice;
        $bookClosingService->validateDate($transaction->date->format('Y-m-d'));

        DB::transaction(function () use ($transaction, $service, $sender, $receiver) {
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

            if ($sender instanceof \App\Models\Addrbook) {
                $service->syncStatFromLatestTransaction($sender);
            }
            if ($receiver instanceof \App\Models\Addrbook) {
                $service->syncStatFromLatestTransaction($receiver);
            }
        });

        app(StandaloneInvoiceSettlement::class)->reconcileByNumber($invoiceNumber, Auth::user());

        return redirect()->route('transactions.index')->with('success', 'Transaction moved to deleted.');
    }

    /**
     * @return array{
     *     can_create: bool,
     *     banks: \Illuminate\Support\Collection<int, \App\Models\Addrbook>,
     *     default_account: array{id: int, name: string}|null,
     *     min_date: string,
     *     default_date: string,
     *     default_amount: float,
     *     linked: \Illuminate\Support\Collection<int, Transaction>
     * }
     */
    private function sellCashInFormData(?\App\Models\User $user, float $defaultAmount = 0.0): array
    {
        $today = now()->toDateString();
        $minDate = app(BookClosingService::class)->getMinAllowedDate()->toDateString();

        return [
            'can_create' => $user !== null && (
                $user->is_superadmin
                || $user->can(Transaction::getPermissions()['type-cash-in'])
            ),
            'banks' => \App\Models\Addrbook::query()
                ->where('type', \App\Models\Addrbook::TYPE_BANK)
                ->orderBy('name')
                ->get(),
            'default_account' => $user
                ? app(UserPreferenceService::class)->defaultCashAccount($user, true)
                : null,
            'min_date' => $minDate,
            'default_date' => $today < $minDate ? $minDate : $today,
            'default_amount' => $defaultAmount,
            'linked' => collect(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $invoiceSettlement
     * @return array<string, mixed>|null
     */
    private function sellCashInShowData(Transaction $transaction, ?array $invoiceSettlement): ?array
    {
        if ((int) $transaction->type !== Transaction::TYPE_SELL) {
            return null;
        }

        $defaultAmount = $transaction->displayGrandTotal();
        if ($invoiceSettlement && (float) ($invoiceSettlement['remaining'] ?? 0) > 0.009) {
            $defaultAmount = (float) $invoiceSettlement['remaining'];
        }

        $data = $this->sellCashInFormData(Auth::user(), $defaultAmount);
        $receiver = $transaction->receiver;
        $receiverOk = $receiver && in_array((int) $receiver->type, \App\Models\Addrbook::cashPartyTypes(), true);
        $data['can_create'] = $data['can_create']
            && (int) $transaction->status === Transaction::STATUS_COMPLETED
            && $receiverOk;
        $data['linked'] = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('invoice', $transaction->invoice)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $data;
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
            'create_transaction' => $user->can($perms['create']),
            'edit_transaction' => $user->can($perms['edit']),
            'delete_transaction' => $user->can($perms['delete']),
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
