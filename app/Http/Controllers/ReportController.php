<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Item;
use App\Models\MonthlyAccountSummary;
use App\Models\MonthlyCategorySummary;
use App\Models\WarehouseCompare;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function nettCashSby(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_nett_cash']);

        $datesNow = Carbon::now();
        $month = $request->input('month', $datesNow->month);
        $year = $request->input('year', $datesNow->year);

        // Get list of relevant contacts (Customers & Resellers)
        $customers = Addrbook::whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->orderBy('name')
            ->get();

        $customerList = $customers->where('type', Addrbook::TYPE_CUSTOMER)->values();
        $resellerList = $customers->where('type', Addrbook::TYPE_RESELLER)->values();

        // Optimized Query using Summary Table
        $summaries = MonthlyAccountSummary::where('year', $year)
            ->where('month', $month)
            ->whereIn('addrbook_id', $customers->pluck('id'))
            ->get();

        $prepareReport = function ($list) use ($summaries) {
            $report = [
                'cashIn' => [],
                'cashOut' => [],
                'sell' => [],
                'return' => [],
                'nettCash' => 0,
                'nettSell' => 0,
            ];

            foreach ($list as $item) {
                $s = $summaries->firstWhere('addrbook_id', $item->id);
                // We store raw net values, but for report display we usually want ABS
                $report['cashIn'][$item->id] = $s ? (float) abs($s->cash_in) : 0;
                $report['cashOut'][$item->id] = $s ? (float) abs($s->cash_out) : 0;
                $report['sell'][$item->id] = $s ? (float) abs($s->sell) : 0;
                $report['return'][$item->id] = $s ? (float) abs($s->return) : 0;
            }

            // Nett Cash calculation: Total In + Total Out (Out is already negative in DB, so adding them gives Net)
            // But if we use ABS above, we must do: In - Out
            $report['nettCash'] = array_sum($report['cashIn']) - array_sum($report['cashOut']);
            $report['nettSell'] = array_sum($report['sell']) - array_sum($report['return']);

            return $report;
        };

        $customerReport = $prepareReport($customerList);
        $resellerReport = $prepareReport($resellerList);

        $yearList = range(date('Y'), 2019);

        return Inertia::render('Reports/NettCashSby', [
            'customerList' => $customerList,
            'resellerList' => $resellerList,
            'customerReport' => $customerReport,
            'resellerReport' => $resellerReport,
            'filters' => ['month' => (int) $month, 'year' => (int) $year],
            'yearList' => $yearList,
            'datesNow' => ['month' => $datesNow->month, 'year' => $datesNow->year],
        ]);
    }

    public function cashFlow(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_cash_flow']);

        $datesNow = Carbon::now();
        $month = $request->input('month');
        $year = $request->input('year', $datesNow->year);

        $types = [
            Addrbook::TYPE_CUSTOMER,
            Addrbook::TYPE_RESELLER,
            Addrbook::TYPE_BANK,
            Addrbook::TYPE_ACCOUNT,
        ];

        // Optimized Query using Summary Table
        $query = MonthlyCategorySummary::where('year', $year)
            ->whereIn('addrbook_type', $types);

        if ($month) {
            $query->where('month', $month);
        }

        $results = $query->selectRaw('
            addrbook_type as type_id,
            SUM(cash_in) as cash_in_total,
            SUM(cash_out) as cash_out_total,
            SUM(sell) as sell_total,
            SUM(buy) as buy_total,
            SUM(return) as return_total,
            SUM(return_supplier) as return_supplier
        ')
        ->groupBy('addrbook_type')
        ->get();

        $results->transform(function ($item) {
            $item->cash_in_total = abs((float) $item->cash_in_total);
            $item->cash_out_total = abs((float) $item->cash_out_total);
            $item->sell_total = abs((float) $item->sell_total);
            $item->buy_total = abs((float) $item->buy_total);
            $item->return_total = abs((float) $item->return_total);
            $item->return_supplier = abs((float) $item->return_supplier);
            return $item;
        });

        $addrbookTypes = collect(Addrbook::getTypes());        $results->transform(function ($item) use ($addrbookTypes) {
            $type = $addrbookTypes->firstWhere('id', $item->type_id);
            $item->type_name = $type ? $type['name'] : 'Unknown';

            return $item;
        });

        $yearList = range(date('Y'), 2019);

        return Inertia::render('Reports/CashFlow', [
            'groupBySender' => $results, // In category summary, sender/receiver are combined by type
            'groupByReceiver' => [],
            'filters' => ['month' => $month ? (int) $month : null, 'year' => (int) $year],
            'yearList' => $yearList,
            'datesNow' => ['month' => $datesNow->month, 'year' => $datesNow->year],
        ]);
    }

    public function compare(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_compare']);

        $user = Auth::user();
        $selectedWarehouses = WarehouseCompare::with('warehouse')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $warehouseIds = $selectedWarehouses->pluck('warehouse_id')->toArray();

        $query = Item::query()->select('items.id', 'items.name', 'items.code');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('items.name', 'like', "%{$request->search}%")
                    ->orWhere('items.code', 'like', "%{$request->search}%");
            });
        }

        foreach ($warehouseIds as $whId) {
            $query->selectRaw("(SELECT SUM(quantity) FROM warehouse_items WHERE item_id = items.id AND warehouse_id = ?) as wh_{$whId}", [$whId]);
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        if (str_starts_with($sort, 'wh_')) {
            $query->orderByRaw("($sort) $direction");
        } else {
            $query->orderBy($sort, $direction);
        }

        $items = $query->paginate(50)->withQueryString();
        $allWarehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return Inertia::render('Reports/Compare', [
            'items' => $items,
            'selectedWarehouses' => $selectedWarehouses,
            'allWarehouses' => $allWarehouses,
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function inventoryHealth(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');

        $query = Item::query()
            ->select('items.id', 'items.name', 'items.code');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%");
            });
        }

        // Metrics from Summary Table
        // 1. Sales last 30 days (Fast Moving)
        $date30 = now()->subDays(30)->toDateString();
        // 2. Sales last 90 days (Dead Stock detection)
        $date90 = now()->subDays(90)->toDateString();

        $query->selectRaw('
            (SELECT SUM(qty_sell) FROM daily_inventory_summaries WHERE item_id = items.id AND date >= ? '.($warehouseId ? "AND warehouse_id = $warehouseId" : '').') as sold_30,
            (SELECT SUM(qty_sell) FROM daily_inventory_summaries WHERE item_id = items.id AND date >= ? '.($warehouseId ? "AND warehouse_id = $warehouseId" : '').') as sold_90,
            (SELECT MAX(date) FROM daily_inventory_summaries WHERE item_id = items.id AND qty_sell > 0 '.($warehouseId ? "AND warehouse_id = $warehouseId" : '').') as last_sold_at,
            (SELECT SUM(quantity) FROM warehouse_items WHERE item_id = items.id '.($warehouseId ? "AND warehouse_id = $warehouseId" : '').') as current_stock
        ', [$date30, $date90]);

        $items = $query->paginate(50)->withQueryString();

        $warehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return Inertia::render('Reports/InventoryHealth', [
            'items' => $items,
            'warehouses' => $warehouses,
            'filters' => $request->only(['warehouse_id', 'search']),
        ]);
    }

    public function storeCompare(Request $request)
    {
        $request->validate([
            'warehouse_id' => [
                'required',
                Rule::unique('warehouse_compares', 'warehouse_id')->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],
        ]);

        WarehouseCompare::create(['user_id' => Auth::id(), 'warehouse_id' => $request->warehouse_id]);

        return back()->with('success', 'Warehouse added.');
    }

    public function destroyCompare(WarehouseCompare $compare)
    {
        if ($compare->user_id !== Auth::id()) {
            abort(403);
        }
        $compare->delete();

        return back()->with('success', 'Warehouse removed.');
    }
}
