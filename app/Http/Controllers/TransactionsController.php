<?php

namespace App\Http\Controllers;

use App\Models\Jubeliosync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionsController extends Controller
{
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['view']);
        $transactions = \App\Models\Transaction::with(['sender', 'receiver'])
            ->when($request->invoice_number, function ($query, $invoice) {
                $query->where('invoice_number', 'like', "%{$invoice}%");
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->min_total, function ($query, $min) {
                $query->where('grand_total', '>=', $min);
            })
            ->when($request->max_total, function ($query, $max) {
                $query->where('grand_total', '<=', $max);
            })
            ->when($request->from, function ($query, $from) {
                $query->whereDate('date', '>=', $from);
            })
            ->when($request->to, function ($query, $to) {
                $query->whereDate('date', '<=', $to);
            });

        // Default Sort
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        $validSorts = ['date', 'invoice_number', 'type', 'grand_total'];

        if (in_array($sort, $validSorts)) {
            $transactions->orderBy($sort, $direction)
                ->orderBy('id', 'desc');
        } else {
            $transactions->orderBy('date', 'desc')
                ->orderBy('id', 'desc');
        }

        $transactions = $transactions->paginate(50)
            ->withQueryString();

        return inertia('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['from', 'to', 'sort', 'direction', 'type', 'invoice_number', 'min_total', 'max_total']),
            'can' => [
                'create_transaction' => Auth::user()->can(\App\Models\Transaction::getPermissions()['create']),
                'delete_transaction' => Auth::user()->can(\App\Models\Transaction::getPermissions()['delete']),
                'type_buy' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-buy']),
                'type_sell' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-sell']),
                'type_move' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-move']),
                'cash_in' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-cash-in']),
                'cash_out' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-cash-out']),
                'transfer' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-transfer']),
                'adjust' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-adjust']),
                'return' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-return']),
                'return_supplier' => Auth::user()->can(\App\Models\Transaction::getPermissions()['type-return-supplier']),
            ],
        ]);
    }

    public function create(string $type, \App\Services\BookClosingService $bookClosingService)
    {
        $permissionKey = 'type_'.str_replace('-', '_', $type);
        $permissions = \App\Models\Transaction::getPermissions();

        if (isset($permissions[$permissionKey])) {
            \Illuminate\Support\Facades\Gate::authorize($permissions[$permissionKey]);
        } else {
            \Illuminate\Support\Facades\Gate::authorize($permissions['create']);
        }

        $config = config("transaction_rules.{$type}");

        if (! $config) {
            abort(404, "Transaction type '$type' not supported.");
        }

        // Hydrate config with routes and labels
        $config['sender_route'] = route('transactions.lookup', ['type' => $type, 'role' => 'sender', 'addrbook_type' => $config['sender_type'] ?? null]);
        $config['receiver_route'] = route('transactions.lookup', ['type' => $type, 'role' => 'receiver', 'addrbook_type' => $config['receiver_type'] ?? null]);

        // Helper to get label
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

        return inertia('Transactions/Create', [
            'type' => $type,
            'config' => $config,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'suppliers' => [],
            'customers' => [],
            'warehouses' => [],
            'items' => [],
        ]);
    }

    public function store(\Illuminate\Http\Request $request, \App\Services\TransactionService $service, \App\Services\BookClosingService $bookClosingService)
    {
        $validatedType = $request->input('type');
        $permissionKey = 'type_'.str_replace('-', '_', $validatedType);
        $permissions = \App\Models\Transaction::getPermissions();

        if (isset($permissions[$permissionKey])) {
            \Illuminate\Support\Facades\Gate::authorize($permissions[$permissionKey]);
        } else {
            \Illuminate\Support\Facades\Gate::authorize($permissions['create']);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'required|string', // buy, sell
            'sender_id' => 'required',
            'receiver_id' => 'required',
            'invoice_number' => 'nullable|string',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'adjustment' => 'nullable|numeric',
        ]);

        $bookClosingService->validateDate($validated['date']);

        $config = config("transaction_rules.{$validated['type']}");

        $transaction = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $config, $service) {

            // "simpan sender_type id receiver_type id nya bukan app\model\addrbook"
            $senderModel = \App\Models\Addrbook::find($validated['sender_id']);
            $senderType = $senderModel ? $senderModel->type : \App\Models\Addrbook::TYPE_OTHER;

            $receiverModel = \App\Models\Addrbook::find($validated['receiver_id']);
            $receiverType = $receiverModel ? $receiverModel->type : \App\Models\Addrbook::TYPE_OTHER;

            $trx = \App\Models\Transaction::create([
                'date' => $validated['date'],
                'type' => $config['id'] ?? 0,
                'sender_type' => $senderType,
                'sender_id' => $validated['sender_id'],
                'receiver_type' => $receiverType,
                'receiver_id' => $validated['receiver_id'],
                'notes' => $validated['note'] ?? null,
                'user_id' => Auth::id(),
                'status' => \App\Models\Transaction::STATUS_COMPLETED,
                'grand_total' => 0,
                'total_items' => 0,
                'adjustment' => $validated['adjustment'] ?? 0,
                'submit_type' => 1,
            ]);

            // If invoice_number is empty, use the ID
            if (empty($trx->invoice_number)) {
                $trx->update(['invoice_number' => $trx->id]);
            }

            $grandTotal = 0;
            $totalItems = 0;
            $ppnRate = (float) \App\Models\Setting::getValue('ppn_rate', 11) / 100;

            // PPN Logic: Check if sender (for buy) or receiver (for sell) has PPN active
            $isPpn = false;
            if ($config['id'] === \App\Models\Transaction::TYPE_BUY) {
                $sender = \App\Models\Addrbook::find($validated['sender_id']);
                $isPpn = $sender ? $sender->ppn : false;
            } elseif ($config['id'] === \App\Models\Transaction::TYPE_SELL) {
                $receiver = \App\Models\Addrbook::find($validated['receiver_id']);
                $isPpn = $receiver ? $receiver->ppn : false;
            }

            // Logic: If sender is a Warehouse (ID 2), check stock. TYPE_V_WAREHOUSE (5) allows negative stock.
            $checkStock = $senderType == \App\Models\Addrbook::TYPE_WAREHOUSE;

            if ($checkStock) {
                foreach ($validated['items'] as $item) {
                    $wi = \App\Models\WarehouseItem::where('warehouse_id', $validated['sender_id'])
                        ->where('item_id', $item['item_id'])
                        ->first();

                    $available = $wi ? $wi->quantity : 0;

                    if ($item['quantity'] > (float) $available) {
                        $itemModel = \App\Models\Item::find($item['item_id']);
                        $itemName = $itemModel ? $itemModel->name : 'ID: '.$item['item_id'];
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => ["Insufficient stock for item: {$itemName}. Available: ".((float) $available)],
                        ]);
                    }
                }
            }

            foreach ($validated['items'] as $item) {
                $total = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                $grandTotal += $total;
                $totalItems += $item['quantity'];

                $trx->details()->create([
                    'item_id' => $item['item_id'],
                    'date' => $trx->date,
                    'transaction_type' => $trx->type,
                    'sender_id' => $trx->sender_id,
                    'receiver_id' => $trx->receiver_id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'total' => $total,
                    'notes' => $item['note'] ?? null,
                ]);
            }

            $discountPercent = $validated['discount_percent'] ?? 0;
            $discountAmount = $grandTotal * ($discountPercent / 100);
            $afterDiscount = $grandTotal - $discountAmount;
            $adjustment = $validated['adjustment'] ?? 0;
            $totalBeforeTax = $afterDiscount + $adjustment;

            $taxAmount = $isPpn ? ($totalBeforeTax * $ppnRate) : 0;

            $trx->total = $grandTotal;
            $trx->discount = $discountAmount;
            $trx->adjustment = $adjustment;
            $trx->tax_amount = $taxAmount;

            $finalTotal = $totalBeforeTax + $taxAmount;
            $trx->grand_total = ($config['id'] === \App\Models\Transaction::TYPE_SELL || $config['id'] === \App\Models\Transaction::TYPE_RETURN_SUPPLIER) ? -$finalTotal : $finalTotal;
            $trx->total_items = $totalItems;
            $trx->save();

            $trx->load(['details', 'sender', 'receiver']);
            $service->handleTransaction($trx);

            return $trx;
        });

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction created.');
    }

    public function show(\App\Models\Transaction $transaction)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['show']);
        $transaction->load(['details.item', 'sender', 'receiver', 'user', 'submitByA', 'submitByB']);

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

        // Refined Sync Relevance Logic
        $isManual = $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_MANUAL;
        $isFromJubelio = $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_JUBELIO;

        $syncRelevantA = in_array($transaction->type, [\App\Models\Transaction::TYPE_SELL, \App\Models\Transaction::TYPE_RETURN_SUPPLIER, \App\Models\Transaction::TYPE_MOVE]);
        $syncRelevantB = in_array($transaction->type, [\App\Models\Transaction::TYPE_BUY, \App\Models\Transaction::TYPE_RETURN, \App\Models\Transaction::TYPE_MOVE]);

        $jubSyncA = Jubeliosync::where('warehouse_id', $transaction->sender_id)->first();
        $jubSyncB = Jubeliosync::where('warehouse_id', $transaction->receiver_id)->first();

        // Only expose Jubelio location if it's MANUAL AND mapped AND relevant to this transaction type
        $transaction->jubelio_a = ($isManual && $jubSyncA && $syncRelevantA) ? $jubSyncA->jubelio_location_name : null;
        $transaction->jubelio_b = ($isManual && $jubSyncB && $syncRelevantB) ? $jubSyncB->jubelio_location_name : null;

        $syncedWarehouseIds = Jubeliosync::pluck('warehouse_id')->toArray();
        $sync_cek = null;

        if ($isManual) {
            if (in_array($transaction->type, [\App\Models\Transaction::TYPE_SELL, \App\Models\Transaction::TYPE_RETURN_SUPPLIER])) {
                $sync_cek = in_array($transaction->sender_id, $syncedWarehouseIds) ? 'S' : null;
            } elseif (in_array($transaction->type, [\App\Models\Transaction::TYPE_BUY, \App\Models\Transaction::TYPE_RETURN])) {
                $sync_cek = in_array($transaction->receiver_id, $syncedWarehouseIds) ? 'R' : null;
            } elseif ($transaction->type == \App\Models\Transaction::TYPE_MOVE) {
                $senderSynced = in_array($transaction->sender_id, $syncedWarehouseIds);
                $receiverSynced = in_array($transaction->receiver_id, $syncedWarehouseIds);

                if ($senderSynced && $receiverSynced) {
                    $sync_cek = 'B';
                } elseif ($senderSynced) {
                    $sync_cek = 'S';
                } elseif ($receiverSynced) {
                    $sync_cek = 'R';
                }
            }
        }

        $transaction->sync_cek = $sync_cek;

        // Determine actual sync state for the badges (Manual transactions only)
        $transaction->a_synced = $isManual && (bool) $transaction->a_submit_by;
        $transaction->b_synced = $isManual && (bool) $transaction->b_submit_by;
        $transaction->is_from_jubelio = $isFromJubelio;

        return inertia('Transactions/Show', [
            'transaction' => $transaction,
            'config' => [
                'sender_label' => $getLabel('sender'),
                'receiver_label' => $getLabel('receiver'),
                'type_slug' => $typeSlug,
            ],
            'can' => [
                'delete_transaction' => Auth::user()->can(\App\Models\Transaction::getPermissions()['delete']),
                'edit_transaction' => Auth::user()->can(\App\Models\Transaction::getPermissions()['edit']),
                'bank_hidden_balance' => Auth::user()->can('addrbook-bank-account-hidden-balance'),
            ],
        ]);
    }

    public function cashIn(\App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-cash-in']);
        $bankList = \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name', 'asc')->get();

        return inertia('Transactions/Cash', [
            'bankList' => $bankList,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'type' => 'in',
        ]);
    }

    public function storeCashIn(Request $request, \App\Services\TransactionService $service, \App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-cash-in']);
        $validated = $request->validate([
            'date' => 'required|date',
            'account_id' => 'required|exists:addrbooks,id', // Bank Account
            'items' => 'required|array|min:1',
            'items.*.customer_id' => 'required|exists:addrbooks,id',
            'items.*.invoice_number' => 'nullable|string',
            'items.*.note' => 'nullable|string',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        $bookClosingService->validateDate($validated['date']);

        $createdIds = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $service, &$createdIds) {
            foreach ($validated['items'] as $item) {
                // Determine sender type
                $sender = \App\Models\Addrbook::find($item['customer_id']);
                $senderType = $sender ? $sender->type : \App\Models\Addrbook::TYPE_OTHER;

                // Determine receiver type (Bank)
                $receiver = \App\Models\Addrbook::find($validated['account_id']);
                $receiverType = $receiver ? $receiver->type : \App\Models\Addrbook::TYPE_BANK;

                $trx = \App\Models\Transaction::create([
                    'date' => $validated['date'],
                    'type' => \App\Models\Transaction::TYPE_CASH_IN,
                    'sender_type' => $senderType,
                    'sender_id' => $item['customer_id'],
                    'receiver_type' => $receiverType,
                    'receiver_id' => $validated['account_id'],
                    'invoice_number' => $item['invoice_number'] ?? null,
                    'notes' => $item['note'] ?? null,
                    'user_id' => Auth::id(),
                    'status' => \App\Models\Transaction::STATUS_COMPLETED,
                    'grand_total' => $item['total'],
                    'total_items' => 0,
                    'adjustment' => 0,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'submit_type' => 1,
                ]);

                if (empty($trx->invoice_number)) {
                    $trx->update(['invoice_number' => $trx->id]);
                }

                $service->handleTransaction($trx);
                $createdIds[] = $trx->id;
            }
        });

        return redirect()->route('transactions.show', end($createdIds))->with('success', 'Cash In records created: #'.implode(', #', $createdIds));
    }

    public function cashOut(\App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-cash-out']);
        $bankList = \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name', 'asc')->get();

        return inertia('Transactions/Cash', [
            'bankList' => $bankList,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
            'type' => 'out',
        ]);
    }

    public function storeCashOut(Request $request, \App\Services\TransactionService $service, \App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-cash-out']);
        $validated = $request->validate([
            'date' => 'required|date',
            'account_id' => 'required|exists:addrbooks,id', // Bank Account
            'items' => 'required|array|min:1',
            'items.*.customer_id' => 'required|exists:addrbooks,id',
            'items.*.invoice_number' => 'nullable|string',
            'items.*.note' => 'nullable|string',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        $bookClosingService->validateDate($validated['date']);

        $createdIds = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $service, &$createdIds) {
            foreach ($validated['items'] as $item) {
                // Determine sender type (Bank)
                $sender = \App\Models\Addrbook::find($validated['account_id']);
                $senderType = $sender ? $sender->type : \App\Models\Addrbook::TYPE_BANK;

                // Determine receiver type (Customer/Supplier/etc)
                $receiver = \App\Models\Addrbook::find($item['customer_id']);
                $receiverType = $receiver ? $receiver->type : \App\Models\Addrbook::TYPE_OTHER;

                $trx = \App\Models\Transaction::create([
                    'date' => $validated['date'],
                    'type' => \App\Models\Transaction::TYPE_CASH_OUT,
                    'sender_type' => $senderType,
                    'sender_id' => $validated['account_id'],
                    'receiver_type' => $receiverType,
                    'receiver_id' => $item['customer_id'],
                    'invoice_number' => $item['invoice_number'] ?? null,
                    'notes' => $item['note'] ?? null,
                    'user_id' => Auth::id(),
                    'status' => \App\Models\Transaction::STATUS_COMPLETED,
                    'grand_total' => -$item['total'], // Negative for Cash Out
                    'total_items' => 0,
                    'adjustment' => 0,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'submit_type' => 1,
                ]);

                if (empty($trx->invoice_number)) {
                    $trx->update(['invoice_number' => $trx->id]);
                }

                $service->handleTransaction($trx);
                $createdIds[] = $trx->id;
            }
        });

        return redirect()->route('transactions.show', end($createdIds))->with('success', 'Cash Out records created: #'.implode(', #', $createdIds));
    }

    public function transfer(\App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-transfer']);
        $bankList = \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_BANK)->orderBy('name', 'asc')->get();

        return inertia('Transactions/Transfer', [
            'bankList' => $bankList,
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function storeTransfer(\Illuminate\Http\Request $request, \App\Services\TransactionService $service, \App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-transfer']);
        $validated = $request->validate([
            'date' => 'required|date',
            'sender' => 'required|exists:addrbooks,id',
            'receiver' => 'required|exists:addrbooks,id|different:sender',
            'invoice' => 'nullable|string',
            'description' => 'nullable|string',
            'total' => 'required|numeric|min:0',
        ]);

        $bookClosingService->validateDate($validated['date']);

        $trx = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $service) {
            $sender = \App\Models\Addrbook::find($validated['sender']);
            $receiver = \App\Models\Addrbook::find($validated['receiver']);

            $trx = \App\Models\Transaction::create([
                'date' => $validated['date'],
                'type' => \App\Models\Transaction::TYPE_TRANSFER,
                'sender_type' => $sender->type,
                'sender_id' => $validated['sender'],
                'receiver_type' => $receiver->type,
                'receiver_id' => $validated['receiver'],
                'invoice_number' => $validated['invoice'] ?? null,
                'notes' => $validated['description'] ?? null,
                'user_id' => Auth::id(),
                'status' => \App\Models\Transaction::STATUS_COMPLETED,
                'grand_total' => $validated['total'],
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'tax_amount' => 0,
                'submit_type' => 1,
            ]);

            if (empty($trx->invoice_number)) {
                $trx->update(['invoice_number' => $trx->id]);
            }

            $service->handleTransaction($trx);

            return $trx;
        });

        return redirect()->route('transactions.show', $trx)->with('success', 'Transfer record created: #'.$trx->id);
    }

    public function adjust(\App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-adjust']);

        return inertia('Transactions/Adjust', [
            'min_date' => $bookClosingService->getMinAllowedDate()->toDateString(),
        ]);
    }

    public function storeAdjust(\Illuminate\Http\Request $request, \App\Services\TransactionService $service, \App\Services\BookClosingService $bookClosingService)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['type-adjust']);
        $validated = $request->validate([
            'date' => 'required|date',
            'invoice' => 'nullable|string',
            'sender' => 'required|exists:addrbooks,id', // Debit(+)
            'receiver' => 'required|exists:addrbooks,id|different:sender', // Credit(+)
            'description' => 'nullable|string',
            'total' => 'required|numeric|min:0',
        ]);

        $bookClosingService->validateDate($validated['date']);

        $trx = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $service) {
            $sender = \App\Models\Addrbook::find($validated['sender']);
            $receiver = \App\Models\Addrbook::find($validated['receiver']);

            // Validation based on legacy
            $notAdjustable = [\App\Models\Addrbook::TYPE_WAREHOUSE, \App\Models\Addrbook::TYPE_V_WAREHOUSE];
            if (in_array($sender->type, $notAdjustable) || in_array($receiver->type, $notAdjustable)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['receiver' => 'Cannot adjust warehouse or virtual accounts']);
            }

            if ($sender->type != \App\Models\Addrbook::TYPE_ACCOUNT && $receiver->type != \App\Models\Addrbook::TYPE_ACCOUNT) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sender' => 'Adjust requires at least 1 journal account']);
            }

            if ($sender->type == \App\Models\Addrbook::TYPE_ACCOUNT && $receiver->type == \App\Models\Addrbook::TYPE_ACCOUNT) {
                throw \Illuminate\Validation\ValidationException::withMessages(['receiver' => 'Cannot adjust 2 journal accounts']);
            }

            $trx = \App\Models\Transaction::create([
                'date' => $validated['date'],
                'type' => \App\Models\Transaction::TYPE_ADJUST,
                'sender_type' => $sender->type,
                'sender_id' => $validated['sender'],
                'receiver_type' => $receiver->type,
                'receiver_id' => $validated['receiver'],
                'invoice_number' => $validated['invoice'] ?? null,
                'notes' => $validated['description'] ?? null,
                'user_id' => Auth::id(),
                'status' => \App\Models\Transaction::STATUS_COMPLETED,
                'grand_total' => $validated['total'],
                'total_items' => 0,
                'adjustment' => 0,
                'discount' => 0,
                'tax_amount' => 0,
                'submit_type' => 1,
            ]);

            if (empty($trx->invoice_number)) {
                $trx->update(['invoice_number' => $trx->id]);
            }

            $service->handleTransaction($trx);

            return $trx;
        });

        return redirect()->route('transactions.show', $trx)->with('success', 'Adjust record created: #'.$trx->id);
    }

    public function destroy(\App\Models\Transaction $transaction, \App\Services\TransactionService $service)
    {
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['delete']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $service) {
            // 1. Reverse side effects (stock, balance, etc.)
            $service->revertTransaction($transaction);

            // 2. Copy Transaction to DeletedTransaction
            $transactionData = $transaction->getAttributes();
            $transactionData['deleted_at'] = now();
            \App\Models\DeletedTransaction::create($transactionData);

            // 3. Copy Details to DeletedTransactionDetail
            foreach ($transaction->details as $detail) {
                $detailData = $detail->getAttributes();
                $detailData['deleted_at'] = now();
                \App\Models\DeletedTransactionDetail::create($detailData);
            }

            // 4. Physically remove from main tables (Moving the data)
            $transaction->details()->forceDelete();
            $transaction->forceDelete();
        });

        return redirect()->route('transactions.index')->with('success', 'Transaction moved to deleted.');
    }

    public function batchParse(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:5120',
            'warehouse_id' => 'nullable|integer',
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();

        $array = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Read first line to detect delimiter
            $firstLine = fgets($handle);
            rewind($handle);

            // Count occurrences of , and ;
            $commas = substr_count($firstLine, ',');
            $semicolons = substr_count($firstLine, ';');
            $delimiter = $semicolons > $commas ? ';' : ',';

            $first = true;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (count($data) >= 3) {
                    // Header detection: if first line's 2nd column (quantity) is not numeric, skip it.
                    if ($first && ! is_numeric(trim($data[1]))) {
                        $first = false;

                        continue;
                    }
                    $first = false;
                    $array[] = [
                        'code' => trim($data[0]),
                        'qty' => trim($data[1]),
                        'price' => trim($data[2]),
                    ];
                }
            }
            fclose($handle);
        }

        if (empty($array)) {
            return response()->json([
                'error' => 'Failed to parse CSV or file is empty. Expected format: code,qty,price. Make sure to use comma (,) or semicolon (;) as delimiter.',
            ], 422);
        }

        $codes = collect($array)->pluck('code')->unique();
        $whid = $request->warehouse_id;

        $itemsQuery = \App\Models\Item::whereIn('code', $codes);

        if ($whid) {
            $itemsQuery->with(['warehouseItems' => function ($query) use ($whid) {
                $query->where('warehouse_id', $whid);
            }]);
        }

        $items = $itemsQuery->get()->keyBy('code');

        $dataList = [];
        foreach ($array as $row) {
            $code = $row['code'];
            $qty = (float) $row['qty'];
            $price = (float) $row['price'];

            $item = $items[$code] ?? null;

            if ($item) {
                $warehouseQuantity = 0;
                if ($whid && $item->relationLoaded('warehouseItems')) {
                    $whItem = $item->warehouseItems->first();
                    $warehouseQuantity = $whItem ? (float) $whItem->quantity : 0;
                }

                $dataList[] = [
                    'id' => (string) $item->id,
                    'item_id' => (string) $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'quantity' => $qty,
                    'warehouse_stock' => $warehouseQuantity,
                    'warehouse_id' => $whid,
                    'price' => $price,
                    'discount' => 0,
                    'subtotal' => $qty * $price,
                    'note' => '',
                ];
            }
        }

        return response()->json([
            'data' => $dataList,
            'totalQty' => collect($dataList)->sum('quantity'),
            'totalPrice' => collect($dataList)->sum('subtotal'),
        ]);
    }
}
