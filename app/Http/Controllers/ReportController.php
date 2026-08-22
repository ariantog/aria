<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function inventoryHealth(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-inventory-health']);
        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');
        $query = Item::query();
        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%"));
        } $date30 = now()->subDays(30)->toDateString();
        $date90 = now()->subDays(90)->toDateString();
        $query->selectRaw('(SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_30, (SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_90, (SELECT MAX(td.date) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as last_sold_at, (SELECT SUM(quantity) FROM warehouse_item WHERE item_id=items.id '.($warehouseId ? "AND warehouse_id=$warehouseId" : '').') as current_stock', [Transaction::TYPE_SELL, $date30, Transaction::TYPE_SELL, $date90, Transaction::TYPE_SELL]);
        $items = $query->paginate(50)->withQueryString();
        $warehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return view('reports.inventory-health', ['items' => $items, 'warehouses' => $warehouses, 'filters' => $request->only(['warehouse_id', 'search']), 'flash' => ['success' => session('success'), 'error' => session('error')]]);
    }
}
