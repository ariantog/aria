<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function inventoryHealth(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);
        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');
        $query = Item::query();
        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%"));
        } $date30 = now()->subDays(30)->toDateString();
        $date90 = now()->subDays(90)->toDateString();
        $query->selectRaw('(SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_30, (SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_90, (SELECT MAX(td.date) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as last_sold_at, (SELECT SUM(quantity) FROM warehouse_items WHERE item_id=items.id '.($warehouseId ? "AND warehouse_id=$warehouseId" : '').') as current_stock', [Transaction::TYPE_SELL, $date30, Transaction::TYPE_SELL, $date90, Transaction::TYPE_SELL]);
        $items = $query->paginate(50)->withQueryString();
        $warehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return Inertia::render('Reports/InventoryHealth', ['items' => $items, 'warehouses' => $warehouses, 'filters' => $request->only(['warehouse_id', 'search'])]);
    }

    public function stockIntelligence(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);
        $reportId = $request->query('report_id');
        $reportHistory = \App\Models\StokReport::latest('generet_at')->limit(5)->get(['id', 'generet_at', 'type'])->map(fn ($r) => ['id' => $r->id, 'label' => $r->generet_at->locale('id')->translatedFormat('d M Y H:i')." ({$r->type})"]);
        $latestReport = $reportId ? \App\Models\StokReport::find($reportId) : \App\Models\StokReport::latest('generet_at')->first();
        $data = ['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0, 'links' => []];
        if ($latestReport) {
            $dataQuery = \App\Models\StockData::where('id_stock_report', $latestReport->id);
            if ($request->search) {
                $dataQuery->where(fn ($q) => $q->where('item_name', 'like', "%{$request->search}%")->orWhere('item_id', $request->search));
            } if ($request->performance && $request->performance !== 'all') {
                $dataQuery->where('performance_key', $request->performance);
            } $data = $dataQuery->orderByDesc('score')->paginate(50)->withQueryString();
        }

        return Inertia::render('Reports/StockIntelligence', ['data' => $data, 'reportHistory' => $reportHistory, 'filters' => $request->only(['performance', 'search'])]);
    }

    public function updateStockSettings(Request $request)
    {
        return back()->with('success', 'Updated.');
    }

    public function resetStockSettings()
    {
        return back()->with('success', 'Reset.');
    }

    public function rebalanceDetail(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        return Inertia::render('Reports/RebalanceDetail');
    }

    public function generateManual()
    {
        Artisan::call('app:generate-stock-intelligence');

        return back()->with('success', 'Generated.');
    }
}
