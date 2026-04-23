<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Item;
use App\Models\MonthlyAccountSummary;
use App\Models\MonthlyItemSale;
use App\Models\Transaction;
use App\Models\WarehouseCompare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function nettCashSby(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_nett_cash']);

        $datesNow = now();
        $month = $request->input('month', $datesNow->month);
        $year = $request->input('year', $datesNow->year);

        $customers = Addrbook::whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->orderBy('name')
            ->get(['id', 'name']);

        $summaries = MonthlyAccountSummary::where('month', $month)
            ->where('year', $year)
            ->whereIn('addrbook_id', $customers->pluck('id'))
            ->get()
            ->groupBy('addrbook_id');

        $reportData = $customers->map(function ($cust) use ($summaries) {
            $report = [
                'cashIn' => [],
                'cashOut' => [],
                'sell' => [],
                'return' => [],
                'nettCash' => 0,
                'nettSell' => 0,
            ];

            if (isset($summaries[$cust->id])) {
                foreach ($summaries[$cust->id] as $summary) {
                    $item = [
                        'amount' => (float) $summary->amount,
                        'notes' => $summary->notes,
                    ];

                    switch ($summary->transaction_type) {
                        case Transaction::TYPE_CASH_IN:
                            $report['cashIn'][] = $item;
                            $report['nettCash'] += $item['amount'];
                            break;
                        case Transaction::TYPE_CASH_OUT:
                            $report['cashOut'][] = $item;
                            $report['nettCash'] -= $item['amount'];
                            break;
                        case Transaction::TYPE_SELL:
                            $report['sell'][] = $item;
                            $report['nettSell'] += $item['amount'];
                            break;
                        case Transaction::TYPE_RETURN:
                            $report['return'][] = $item;
                            $report['nettSell'] -= $item['amount'];
                            break;
                    }
                }
            }

            return [
                'id' => $cust->id,
                'name' => $cust->name,
                'details' => $report,
            ];
        })->filter(function ($item) {
            return $item['details']['nettCash'] != 0 || $item['details']['nettSell'] != 0;
        })->values();

        return Inertia::render('Reports/NettCashSby', [
            'reportData' => $reportData,
            'filters' => $request->only(['month', 'year']),
            'yearList' => range(date('Y'), 2019),
        ]);
    }

    public function cashFlow(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_cash_flow']);

        $datesNow = now();
        $month = $request->input('month', $datesNow->month);
        $year = $request->input('year', $datesNow->year);

        $accounts = Addrbook::where('type', Addrbook::TYPE_ACCOUNT)
            ->orderBy('name')
            ->get(['id', 'name']);

        $summaries = MonthlyAccountSummary::where('month', $month)
            ->where('year', $year)
            ->whereIn('addrbook_id', $accounts->pluck('id'))
            ->get()
            ->groupBy('addrbook_id');

        $reportData = $accounts->map(function ($acc) use ($summaries) {
            $totalIn = 0;
            $totalOut = 0;

            if (isset($summaries[$acc->id])) {
                foreach ($summaries[$acc->id] as $summary) {
                    $amt = (float) $summary->amount;
                    if ($amt > 0) {
                        $totalIn += $amt;
                    } else {
                        $totalOut += abs($amt);
                    }
                }
            }

            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'totalIn' => $totalIn,
                'totalOut' => $totalOut,
                'nett' => $totalIn - $totalOut,
            ];
        })->filter(function ($item) {
            return $item['totalIn'] != 0 || $item['totalOut'] != 0;
        })->values();

        return Inertia::render('Reports/CashFlow', [
            'reportData' => $reportData,
            'filters' => $request->only(['month', 'year']),
            'yearList' => range(date('Y'), 2019),
        ]);
    }

    public function compare(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_compare']);

        $userCompares = WarehouseCompare::where('user_id', Auth::id())
            ->with('warehouse:id,name')
            ->get();

        $warehouseIds = $userCompares->pluck('warehouse_id')->toArray();

        $itemQuery = DB::table('warehouse_items')
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity', '>', 0)
            ->select('item_id')
            ->distinct();

        $search = $request->input('search');
        $items = Item::whereIn('id', $itemQuery);

        if ($search) {
            $items->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%");
            });
        }

        $items = $items->orderBy('name')->paginate(50)->withQueryString();

        $stockLevels = DB::table('warehouse_items')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('item_id', $items->pluck('id'))
            ->get()
            ->groupBy('item_id');

        $items->getCollection()->transform(function ($item) use ($stockLevels, $warehouseIds) {
            $stocks = [];
            foreach ($warehouseIds as $whId) {
                $stockRecord = $stockLevels->get($item->id)?->firstWhere('warehouse_id', $whId);
                $stocks[$whId] = $stockRecord ? (float) $stockRecord->quantity : 0;
            }
            $item->stocks = $stocks;

            return $item;
        });

        return Inertia::render('Reports/WarehouseCompare', [
            'items' => $items,
            'userCompares' => $userCompares,
            'allWarehouses' => Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function inventoryHealth(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');

        $query = Item::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%");
            });
        }

        $date30 = now()->subDays(30)->toDateString();
        $date90 = now()->subDays(90)->toDateString();

        $query->selectRaw('
            (SELECT SUM(td.quantity) 
             FROM transaction_details td
             WHERE td.item_id = items.id AND td.transaction_type = ? AND td.date >= ? '
            .($warehouseId ? "AND td.sender_id = $warehouseId" : '').') as sold_30,
            (SELECT SUM(td.quantity) 
             FROM transaction_details td
             WHERE td.item_id = items.id AND td.transaction_type = ? AND td.date >= ? '
            .($warehouseId ? "AND td.sender_id = $warehouseId" : '').') as sold_90,
            (SELECT MAX(td.date) 
             FROM transaction_details td
             WHERE td.item_id = items.id AND td.transaction_type = ? '
            .($warehouseId ? "AND td.sender_id = $warehouseId" : '').') as last_sold_at,
            (SELECT SUM(quantity) FROM warehouse_items WHERE item_id = items.id '.($warehouseId ? "AND warehouse_id = $warehouseId" : '').') as current_stock
        ', [Transaction::TYPE_SELL, $date30, Transaction::TYPE_SELL, $date90, Transaction::TYPE_SELL]);

        $items = $query->paginate(50)->withQueryString();

        $warehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return Inertia::render('Reports/InventoryHealth', [
            'items' => $items,
            'warehouses' => $warehouses,
            'filters' => $request->only(['warehouse_id', 'search']),
        ]);
    }

    public function itemSales(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $query = MonthlyItemSale::with('item:id,name,code');

        if ($request->month) {
            $query->where('month', $request->month);
        }
        if ($request->year) {
            $query->where('year', $request->year);
        }
        if ($request->group_id) {
            $query->where('group_id', $request->group_id);
        }

        $dataList = $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(100)
            ->withQueryString();

        return Inertia::render('Reports/ItemSales', [
            'dataList' => $dataList,
            'filters' => $request->only(['month', 'year', 'group_id']),
            'yearList' => range(date('Y'), 2019),
        ]);
    }

    public function stockIntelligence(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $reportId = $request->query('report_id');

        // Ambil daftar riwayat laporan (5 terakhir)
        $reportHistory = \App\Models\StokReport::latest('generet_at')
            ->limit(5)
            ->get(['id', 'generet_at', 'type'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->generet_at->locale('id')->translatedFormat('d M Y H:i')." ({$r->type})",
            ]);

        // Jika ada report_id, ambil yang spesifik. Jika tidak, ambil yang terbaru.
        $latestReport = $reportId
            ? \App\Models\StokReport::find($reportId)
            : \App\Models\StokReport::latest('generet_at')->first();

        // Perbaikan: Cari laporan yang persis 1 urutan di bawah laporan saat ini (berdasarkan waktu)
        $previousReport = null;
        if ($latestReport) {
            $previousReport = \App\Models\StokReport::where('generet_at', '<', $latestReport->generet_at)
                ->latest('generet_at')
                ->first();
        }

        $previousScores = [];
        if ($previousReport) {
            $previousScores = \App\Models\StockData::where('id_stock_report', $previousReport->id)
                ->pluck('score', 'item_id')
                ->toArray();
        }

        $perfFilter = $request->query('performance', 'all');
        $search = $request->query('search');
        $perPage = 50;

        $dbGenerateDays = \App\Models\Setting::getValue('si_generate_days', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

        // Map English to Indonesian for UI
        $revMap = array_flip(\App\Models\Setting::DAY_MAP);
        $uiGenerateDays = array_map(fn ($en) => $revMap[$en] ?? $en, $dbGenerateDays);

        $settings = [
            'gap_weight' => (float) \App\Models\Setting::getValue('si_gap_weight', 0.2),
            'sale_weight' => (float) \App\Models\Setting::getValue('si_sale_weight', 0.8),
            'max_gap' => (int) \App\Models\Setting::getValue('si_max_gap', 90),
            'max_days' => (int) \App\Models\Setting::getValue('si_max_days', 90),
            'total_rows' => (int) \App\Models\Setting::getValue('si_total_rows', 1000),
            'generate_days' => $uiGenerateDays,
        ];

        // LOGIC NEXT RUN (Requested Flow)
        $nextRun = null;
        $now = now();
        $lastDate = $latestReport ? $latestReport->generet_at->copy() : $now->copy()->subDay();

        if (! empty($dbGenerateDays)) {
            // 1. Bikin array date next dengan loop si_generate_days
            $nextDatesArray = [];
            foreach ($dbGenerateDays as $enDay) {
                // Konversi jadi date (Carbon)
                $d = $lastDate->copy()->next($enDay);
                // Key array isi dengan tanggal day (d) saja
                $nextDatesArray[(int) $d->format('d')] = $d;
            }

            // 2. Urutkan date nya (berdasarkan key tanggal d)
            ksort($nextDatesArray);

            // 3. Bandingkan date sekarang dengan array nextdate mana yang akan lebih dulu
            $nowDay = (int) $now->format('d');
            $nextDayResult = null;

            foreach ($nextDatesArray as $dayNum => $dateObj) {
                // Hitung dengan hari ini juga dengan (d) juga
                if ($dayNum > $nowDay) {
                    $nextDayResult = $dateObj;
                    break;
                }
            }

            // Jika tidak ada yang lebih besar di bulan ini, ambil yang paling awal (bulan depan)
            if (! $nextDayResult && ! empty($nextDatesArray)) {
                $nextDayResult = reset($nextDatesArray);
                // Jika masih di bulan yang sama (karena ksort), pastikan dia jadi bulan depan
                if ($nextDayResult->lte($now)) {
                    $nextDayResult = $nextDayResult->addWeeks(1);
                }
            }

            if ($nextDayResult) {
                $nextRun = $nextDayResult->toDateTimeString();
            }
        }

        $stats = [
            'all' => 0, 'elite' => 0, 'good' => 0, 'active' => 0,
            'lagging' => 0, 'stagnant' => 0, 'deadstock' => 0, 'critical' => 0,
        ];

        $data = [
            'data' => [], 'current_page' => 1, 'last_page' => 1,
            'per_page' => $perPage, 'total' => 0, 'links' => [],
        ];

        $reportInfo = [
            'generet_at' => $latestReport ? $latestReport->generet_at->locale('id')->translatedFormat('d F Y H:i') : 'Never',
            'last_update_days_ago' => $latestReport ? $latestReport->generet_at->locale('id')->diffForHumans() : '-',
            'type' => $latestReport ? $latestReport->type : '-',
            'generet_by' => $latestReport ? ($latestReport->user?->name ?? 'System') : '-',
            'next_run' => $nextRun ? \Illuminate\Support\Carbon::parse($nextRun)->locale('id')->translatedFormat('d F Y H:i') : null,
        ];

        if ($latestReport) {
            $statsRow = \App\Models\StockData::where('id_stock_report', $latestReport->id)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN performance_key = 'elite' THEN 1 ELSE 0 END) as elite,
                    SUM(CASE WHEN performance_key = 'good' THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN performance_key = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN performance_key = 'lagging' THEN 1 ELSE 0 END) as lagging,
                    SUM(CASE WHEN performance_key = 'stagnant' THEN 1 ELSE 0 END) as stagnant,
                    SUM(CASE WHEN performance_key = 'deadstock' THEN 1 ELSE 0 END) as deadstock,
                    SUM(CASE WHEN performance_key = 'critical' THEN 1 ELSE 0 END) as critical
                ")
                ->first();

            $stats = [
                'all' => (int) $statsRow->total,
                'elite' => (int) $statsRow->elite,
                'good' => (int) $statsRow->good,
                'active' => (int) $statsRow->active,
                'lagging' => (int) $statsRow->lagging,
                'stagnant' => (int) $statsRow->stagnant,
                'deadstock' => (int) $statsRow->deadstock,
                'critical' => (int) $statsRow->critical,
            ];

            $dataQuery = \App\Models\StockData::where('id_stock_report', $latestReport->id);

            if ($search) {
                $dataQuery->where(function ($q) use ($search) {
                    $q->where('item_name', 'like', "%{$search}%")
                        ->orWhere('item_id', $search);
                });
            }

            if ($perfFilter && $perfFilter !== 'all') {
                $dataQuery->where('performance_key', $perfFilter);
            }

            $rows = $dataQuery->orderByDesc('score')->paginate($perPage)->withQueryString();

            $rows->getCollection()->transform(function ($r) use ($latestReport, $previousScores) {
                $prevScore = $previousScores[$r->item_id] ?? null;

                return [
                    'item_id' => $r->item_id,
                    'item_name' => $r->item_name,
                    'score' => (float) $r->score,
                    'previous_score' => $prevScore !== null ? (float) $prevScore : null,
                    'performance_key' => $r->performance_key,
                    'performance_level' => $r->performance_level,
                    'gap_days' => $r->gap_days ?? 'NEVER SOLD',
                    'current_warehouse' => [
                        'id' => $r->current_warehouse_id,
                        'name' => $r->current_warehouse_name,
                        'qty' => (int) $r->current_warehouse_qty,
                        'last_sale' => $r->current_warehouse_last_sale ?? 'NEVER SOLD',
                        'days_ago' => $r->current_warehouse_days_ago ?? 'NEVER SOLD',
                    ],
                    'best_performing_warehouse' => $r->best_performing_warehouse_id ? [
                        'name' => $r->best_performing_warehouse_name,
                        'last_sale' => $r->best_performing_warehouse_last_sale,
                        'days_ago' => (int) $r->best_performing_warehouse_days_ago,
                        'qty' => (int) $r->best_performing_warehouse_qty,
                        'id' => $r->best_performing_warehouse_id,
                    ] : null,
                    'audit_reference_date' => $latestReport->generet_at->toDateTimeString(),
                ];
            });

            $data = $rows;
        }

        return Inertia::render('Reports/StockIntelligence', [
            'data' => $data,
            'stats' => $stats,
            'settings' => $settings,
            'reportInfo' => $reportInfo,
            'reportHistory' => $reportHistory,
            'currentReportId' => $latestReport?->id,
            'filters' => [
                'performance' => $perfFilter,
                'search' => $search,
            ],
        ]);
    }

    public function updateStockSettings(Request $request)
    {
        $validated = $request->validate([
            'gap_weight' => 'required|numeric|min:0|max:1',
            'sale_weight' => 'required|numeric|min:0|max:1',
            'max_gap' => 'required|integer|min:1',
            'max_days' => 'required|integer|min:1',
            'total_rows' => 'required|integer|min:100|max:10000',
            'generate_days' => 'required|array',
            'generate_days.*' => 'string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
        ]);

        // Map Indonesian back to English for DB storage
        $enGenerateDays = array_map(fn ($idDay) => \App\Models\Setting::DAY_MAP[$idDay] ?? $idDay, $validated['generate_days']);

        \App\Models\Setting::updateOrCreate(['slug' => 'si_gap_weight'], ['value' => $validated['gap_weight'], 'group' => 'stock_intelligence', 'name' => 'Gap Weight']);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_sale_weight'], ['value' => $validated['sale_weight'], 'group' => 'stock_intelligence', 'name' => 'Sale Weight']);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_max_gap'], ['value' => $validated['max_gap'], 'group' => 'stock_intelligence', 'name' => 'Max Gap']);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_max_days'], ['value' => $validated['max_days'], 'group' => 'stock_intelligence', 'name' => 'Max Days']);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_total_rows'], ['value' => $validated['total_rows'], 'group' => 'stock_intelligence', 'name' => 'Total Rows']);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_generate_days'], ['value' => $enGenerateDays, 'group' => 'stock_intelligence', 'name' => 'Generate Days']);

        return back()->with('success', 'Settings updated.');
    }

    public function resetStockSettings()
    {
        \App\Models\Setting::updateOrCreate(['slug' => 'si_gap_weight'], ['value' => 0.2]);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_sale_weight'], ['value' => 0.8]);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_max_gap'], ['value' => 90]);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_max_days'], ['value' => 90]);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_total_rows'], ['value' => 1000]);
        \App\Models\Setting::updateOrCreate(['slug' => 'si_generate_days'], ['value' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']]);

        return back()->with('success', 'Settings reset to default.');
    }

    public function rebalanceDetail(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $itemId = $request->input('item_id');
        $sourceWhId = $request->input('warehouse_id');

        $item = Item::findOrFail($itemId);
        $sourceWh = Addrbook::findOrFail($sourceWhId);

        $date30 = now()->subDays(30)->toDateString();

        $warehouseStocks = DB::table('addrbooks as a')
            ->leftJoin('warehouse_items as wi', function ($join) use ($itemId) {
                $join->on('a.id', '=', 'wi.warehouse_id')->where('wi.item_id', '=', $itemId);
            })
            ->where('a.type', Addrbook::TYPE_WAREHOUSE)
            ->select(
                'a.id as warehouse_id',
                'a.name as warehouse_name',
                DB::raw('COALESCE(wi.quantity, 0) as current_stock'),
                DB::raw("(SELECT MAX(td.date) 
                          FROM transaction_details td
                          WHERE td.item_id = $itemId AND td.sender_id = a.id AND td.transaction_type = ".Transaction::TYPE_SELL.') as last_sale_date'),
                DB::raw("(SELECT SUM(td.quantity) 
                          FROM transaction_details td
                          WHERE td.item_id = $itemId AND td.sender_id = a.id AND td.date >= '$date30' AND td.transaction_type = ".Transaction::TYPE_SELL.') as sold_30d')
            )
            ->having('current_stock', '>', 0)
            ->orHaving('sold_30d', '>', 0)
            ->orderBy('sold_30d', 'desc')
            ->get();

        $targetWh = $warehouseStocks->where('warehouse_id', '!=', $sourceWhId)->first();

        $recommendation = null;
        if ($targetWh && $targetWh->sold_30d > 0) {
            $sourceStock = $warehouseStocks->where('warehouse_id', $sourceWhId)->first()?->current_stock ?? 0;
            $recommendation = [
                'from_id' => $sourceWh->id,
                'from_name' => $sourceWh->name,
                'to_id' => $targetWh->warehouse_id,
                'to_name' => $targetWh->warehouse_name,
                'demand_30d' => (float) $targetWh->sold_30d,
                'suggested_qty' => min((float) $sourceStock, (float) $targetWh->sold_30d),
            ];
        }

        return Inertia::render('Reports/RebalanceDetail', [
            'item' => $item,
            'sourceWarehouse' => $sourceWh,
            'warehouseStocks' => $warehouseStocks,
            'recommendation' => $recommendation,
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

    public function generateManual()
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $today = now()->toDateString();
        $exists = \App\Models\StokReport::whereDate('generet_at', $today)->exists();

        if ($exists) {
            return back()->with('error', 'Laporan untuk hari ini sudah ada.');
        }

        Artisan::call('app:generate-stock-intelligence', [
            '--type' => 'manual',
            '--user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Laporan berhasil di-generate.');
    }
}
