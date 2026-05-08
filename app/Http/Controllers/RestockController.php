<?php

namespace App\Http\Controllers;

use App\Imports\RestockImport;
use App\Models\Item;
use App\Models\Restock;
use App\Models\RestockHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RestockController extends Controller
{
    public function index(Request $request)
    {
        $columnMap = [
            'restock' => 'restocked_quantity',
            'production' => 'in_production_quantity',
            'shipped' => 'shipped_quantity',
            'missing' => 'missing_quantity',
        ];

        $searchColumn = $request->get('kolom');
        $searchValue = $request->get('code');
        $sortDir = $request->get('order', 'desc');

        $query = Restock::with(['item']);

        if (! empty($searchValue)) {
            $query->whereHas('item', function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('name', 'like', "%{$searchValue}%");
            });
        }

        if (isset($columnMap[$searchColumn])) {
            $query->orderBy($columnMap[$searchColumn], $sortDir);
        } else {
            $query->latest();
        }

        $restocks = $query->paginate(20)->withQueryString();

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $cartCount = count(Cache::get($cacheKey, []));

        return Inertia::render('Restock/Index', [
            'restocks' => $restocks,
            'cartCount' => $cartCount,
            'filters' => $request->only(['kolom', 'code', 'order']),
        ]);
    }

    public function create()
    {
        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        return Inertia::render('Restock/Create', [
            'items' => $items,
        ]);
    }

    public function addItem(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'qty' => 'required|integer|min:1',
        ]);

        $itemData = Item::where('id', $request->code)->orWhere('code', $request->code)->firstOrFail();

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        $found = false;
        foreach ($items as &$item) {
            if ($item['code'] === $itemData->code || $item['code'] === (string) $itemData->id) {
                $item['qty'] += $request->qty;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $items[] = [
                'code' => $itemData->code ?: $itemData->id,
                'name' => $itemData->name,
                'qty' => $request->qty,
                'id' => $itemData->id,
            ];
        }

        Cache::put($cacheKey, $items, now()->addHour());

        return redirect()->route('restock.create')->with('success', 'Item added to restock list.');
    }

    public function removeItem($code)
    {
        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        $items = array_values(array_filter($items, function ($item) use ($code) {
            return $item['code'] != $code;
        }));

        Cache::put($cacheKey, $items, now()->addHour());

        return redirect()->route('restock.create')->with('success', 'Item removed from restock list.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        if (empty($items)) {
            return back()->withErrors(['item' => 'Tidak ada data untuk disimpan']);
        }

        DB::transaction(function () use ($items, $request, $cacheKey) {
            foreach ($items as $item) {
                $itemModel = Item::where('id', $item['id'])->orWhere('code', $item['code'])->first();
                if (! $itemModel) {
                    continue;
                }

                $restock = Restock::where('item_id', $itemModel->id)->lockForUpdate()->first();

                if ($restock) {
                    $before = $restock->restocked_quantity;
                    $restock->increment('restocked_quantity', $item['qty']);
                    $restock->update([
                        'date' => $request->date,
                    ]);
                    $after = $before + $item['qty'];
                } else {
                    $restock = Restock::create([
                        'item_id' => $itemModel->id,
                        'date' => $request->date,
                        'status' => 1,
                        'restocked_quantity' => $item['qty'],
                    ]);
                    $before = 0;
                    $after = $item['qty'];
                }

                RestockHistory::create([
                    'restock_id' => $restock->id,
                    'item_id' => $itemModel->id,
                    'step' => 'restocked',
                    'action' => 'created',
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'qty_changed' => $item['qty'],
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);
            }

            Cache::forget($cacheKey);
        });

        return redirect()->route('restock.index')->with('success', 'Data restock berhasil disimpan');
    }

    public function update($id)
    {
        $restock = Restock::with('item')->findOrFail($id);

        return Inertia::render('Restock/Update', [
            'restock' => $restock,
        ]);
    }

    public function updateQty(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:restocked,production,shipped,missing',
            'qty' => 'required|integer|min:1',
            'invoice' => 'nullable|string',
            'date' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $id) {
            $restock = Restock::lockForUpdate()->findOrFail($id);
            $qty = (int) $request->qty;
            $type = $request->type;

            $beforeValue = 0;
            $afterValue = 0;

            switch ($type) {
                case 'restocked':
                    $beforeValue = $restock->restocked_quantity;
                    $restock->restocked_quantity += $qty;
                    $afterValue = $restock->restocked_quantity;
                    break;
                case 'production':
                    if ($restock->restocked_quantity < $qty) {
                        throw new \Exception('Restocked quantity tidak cukup');
                    }
                    $beforeValue = $restock->in_production_quantity;
                    $restock->restocked_quantity -= $qty;
                    $restock->in_production_quantity += $qty;
                    $afterValue = $restock->in_production_quantity;
                    break;
                case 'shipped':
                    if ($restock->in_production_quantity < $qty) {
                        throw new \Exception('Production quantity tidak cukup');
                    }
                    $beforeValue = $restock->shipped_quantity;
                    $restock->in_production_quantity -= $qty;
                    $restock->shipped_quantity += $qty;
                    $afterValue = $restock->shipped_quantity;
                    break;
                case 'missing':
                    $beforeValue = $restock->missing_quantity;
                    $restock->missing_quantity += $qty;
                    $afterValue = $restock->missing_quantity;
                    break;
            }

            $restock->date = $request->date;
            $restock->save();

            RestockHistory::create([
                'restock_id' => $restock->id,
                'item_id' => $restock->item_id,
                'step' => $type,
                'action' => 'edited',
                'qty_before' => $beforeValue,
                'qty_after' => $afterValue,
                'qty_changed' => $qty,
                'invoice' => $request->invoice,
                'user_id' => auth()->id(),
                'date' => $request->date,
            ]);
        });

        return redirect()->route('restock.index')->with('success', 'Stock updated successfully');
    }

    public function received()
    {
        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $items = Cache::get($cacheKey, []);

        return Inertia::render('Restock/Received', [
            'items' => $items,
        ]);
    }

    public function removeCartItem($code)
    {
        $userId = auth()->id();
        $cacheKey = "gudang_cart_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        $items = array_values(array_filter($items, function ($item) use ($code) {
            return $item['code'] != $code;
        }));

        Cache::put($cacheKey, $items, now()->addHour());

        return redirect()->route('restock.received')->with('success', 'Item removed from restock list.');
    }

    public function receiveStore(Request $request, TransactionService $transactionService)
    {
        $request->validate([
            'date' => 'required|date',
            'invoice' => 'nullable|string',
        ]);

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $items = Cache::get($cacheKey, []);

        if (empty($items)) {
            return back()->withErrors(['gudang' => 'Cart kosong']);
        }

        $errors = [];
        foreach ($items as $row) {
            $restock = Restock::where('item_id', $row['itemId'])->first();
            if (! $restock || $row['quantity'] > $restock->shipped_quantity) {
                $errors[] = "Item {$row['name']} tidak cukup shipped qty";
            }
        }

        if (! empty($errors)) {
            return back()->withErrors(['gudang' => implode(', ', $errors)]);
        }

        $transactionId = DB::transaction(function () use ($items, $request, $transactionService) {
            $ids = collect($items)->pluck('itemId')->toArray();
            $restocks = Restock::whereIn('item_id', $ids)->lockForUpdate()->get()->keyBy('item_id');

            // Create Transaction
            $transaction = Transaction::create([
                'date' => $request->date,
                'type' => Transaction::TYPE_BUY,
                'sender_id' => 373, // Supplier Umum
                'receiver_id' => 2875, // Gudang - Online Sambisari
                'invoice' => $request->invoice,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                'user_id' => auth()->id(),
                'status' => Transaction::STATUS_COMPLETED,
            ]);

            foreach ($items as $row) {
                $restock = $restocks[$row['itemId']];
                $before = $restock->shipped_quantity;
                $after = $before - $row['quantity'];

                $restock->decrement('shipped_quantity', $row['quantity']);

                RestockHistory::create([
                    'restock_id' => $restock->id,
                    'item_id' => $restock->item_id,
                    'step' => 'received',
                    'action' => 'edited',
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'qty_changed' => $row['quantity'],
                    'invoice' => $request->invoice,
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $row['itemId'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'total' => $row['subtotal'],
                    'date' => $request->date,
                    'sender_id' => 373,
                    'receiver_id' => 2875,
                ]);
            }

            $transaction->total = collect($items)->sum('subtotal');
            $transaction->grand_total = $transaction->total;
            $transaction->save();

            $transactionService->handleTransaction($transaction);

            return $transaction->id;
        });

        Cache::forget($cacheKey);

        return redirect()->route('transactions.show', $transactionId)->with('success', 'Transaction created.');
    }

    public function addToGudangCart($id, Request $request)
    {
        $restock = Restock::with('item')->findOrFail($id);

        $request->validate([
            'quantity' => "required|integer|min:1|max:{$restock->shipped_quantity}",
        ]);

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $cart = Cache::get($cacheKey, []);

        foreach ($cart as $row) {
            if ($row['itemId'] == $restock->item_id) {
                return back()->with('error', 'Item sudah ada di cart gudang');
            }
        }

        $cart[] = [
            'itemId' => $restock->item_id,
            'code' => $restock->item->code ?: $restock->item->id,
            'name' => $restock->item->name,
            'quantity' => (int) $request->quantity,
            'price' => $restock->item->price ?? 0,
            'subtotal' => (int) $request->quantity * ($restock->item->price ?? 0),
        ];

        Cache::put($cacheKey, $cart, now()->addHour());

        return back()->with('success', 'Item masuk ke Gudang Cart');
    }

    public function history($restockId)
    {
        $restock = Restock::with('item')->findOrFail($restockId);
        $histories = RestockHistory::with('user')
            ->where('restock_id', $restockId)
            ->orderBy('id', 'desc')
            ->paginate(50);

        return Inertia::render('Restock/History', [
            'restock' => $restock,
            'histories' => $histories,
        ]);
    }

    public function resetSingleQty($id, Request $request)
    {
        $request->validate([
            'type' => 'required|in:restocked,production,shipped',
        ]);

        DB::transaction(function () use ($request, $id) {
            $restock = Restock::lockForUpdate()->findOrFail($id);
            $type = $request->type;
            $before = 0;

            if ($type === 'restocked') {
                $before = $restock->restocked_quantity;
                $restock->restocked_quantity = 0;
            } elseif ($type === 'production') {
                $before = $restock->in_production_quantity;
                $restock->in_production_quantity = 0;
            } elseif ($type === 'shipped') {
                $before = $restock->shipped_quantity;
                $restock->shipped_quantity = 0;
            }

            $restock->save();

            RestockHistory::create([
                'restock_id' => $restock->id,
                'item_id' => $restock->item_id,
                'step' => $type,
                'action' => 'reset',
                'qty_before' => $before,
                'qty_after' => 0,
                'qty_changed' => $before,
                'user_id' => auth()->id(),
                'date' => now(),
            ]);
        });

        return redirect()->route('restock.index')->with('success', ucfirst($request->type).' qty reset ke 0');
    }

    public function uploadExcel()
    {
        return Inertia::render('Restock/UploadExcel');
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
            'date' => 'required|date',
            'type' => 'required|in:restocked,production,shipped,missing',
        ]);

        $import = new RestockImport($request->date, $request->type);
        Excel::import($import, $request->file('file'));

        if (! empty($import->errors)) {
            return back()->withErrors(['import' => implode(', ', $import->errors)]);
        }

        return redirect()->route('restock.index')->with('success', 'Import Restock Berhasil');
    }
}
