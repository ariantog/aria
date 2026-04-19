<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Item;
use App\Models\MonthlyAccountSummary;
use App\Models\MonthlyItemSale;
use App\Models\Transaction;
use App\Models\WarehouseCompare;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        // Get list of relevant contacts (Customers & Resellers)
        $customers = Addrbook::whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Monthly Summary
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

        // 1. Get items that exist in at least one of these warehouses
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

        // 2. Get stock levels for these items across selected warehouses
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

        // 1. Sales last 30 days
        $date30 = now()->subDays(30)->toDateString();
        // 2. Sales last 90 days (Dead Stock detection)
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

        $datesNow = now();
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

    // public function stockIntelligence(Request $request)
    // {
    //     Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

    //     $sellType = Transaction::TYPE_SELL;
    //     $performanceFilter = $request->query('performance');
    //     $daysFilter = $request->query('days');
    //     $search = $request->query('search');

    //     // 1. Ambil Tanggal Transaksi Terakhir di Sistem (Info kesegaran data)
    //     $systemLatestDate = DB::table('transaction_details')->where('transaction_type', $sellType)->max('date') ?? now()->toDateString();

    //     // Titik acuan perhitungan hari tetap hari ini agar "days_ago" akurat sesuai kalender
    //     $today = now();

    //     // 2. Query Utama
    //     $query = DB::table('warehouse_items as wi')
    //         ->join('items as i', 'wi.item_id', '=', 'i.id')
    //         ->join('addrbooks as a', 'wi.warehouse_id', '=', 'a.id')
    //         ->where('wi.quantity', '>', 0)
    //         ->where('a.type', Addrbook::TYPE_WAREHOUSE)
    //         ->select(
    //             'wi.item_id',
    //             'i.name as item_name',
    //             'wi.warehouse_id',
    //             'a.name as warehouse_name',
    //             'wi.quantity as current_qty'
    //         )
    //         ->selectRaw("
    //             (SELECT MAX(date) FROM transaction_details td
    //              WHERE td.item_id = wi.item_id AND td.sender_id = wi.warehouse_id AND td.transaction_type = $sellType) as last_date_sale
    //         ");

    //     if ($search) {
    //         $query->where(function ($q) use ($search) {
    //             $q->where('i.name', 'like', "%$search%");
    //         });
    //     }

    //     // 3. Proses data
    //     $data = $query->get()->map(function ($item) use ($sellType, $today, $systemLatestDate) {
    //         $currentDays = $item->last_date_sale
    //             ? (int) $today->diffInDays(\Carbon\Carbon::parse($item->last_date_sale), true)
    //             : null;

    //         // Cari Gudang Terbaik Secara Global
    //         $bestSale = DB::table('transaction_details as td')
    //             ->join('addrbooks as a', 'td.sender_id', '=', 'a.id')
    //             ->where('td.item_id', $item->item_id)
    //             ->where('td.transaction_type', $sellType)
    //             ->select('td.date', 'td.sender_id', 'a.name as warehouse_name')
    //             ->orderByDesc('td.date')
    //             ->orderByDesc('td.id')
    //             ->first();

    //         $bestWarehouseQty = 0;
    //         $bestDays = null;
    //         if ($bestSale) {
    //             $bestWarehouseQty = (int) DB::table('warehouse_items')
    //                 ->where('item_id', $item->item_id)
    //                 ->where('warehouse_id', $bestSale->sender_id)
    //                 ->value('quantity');
    //             $bestDays = (int) $today->diffInDays(\Carbon\Carbon::parse($bestSale->date), true);
    //         }

    //         // --- Rumus doc/score.md ---
    //         $maxGap = 90; $maxDays = 90;
    //         $gap = 0; $gapScore = 0; $saleScore = 0; $finalScore = 0;
    //         $performanceLevel = '7. Critical (Belum Terjual)';
    //         $perfKey = 'critical';

    //         if ($currentDays === null) {
    //             $gap = 9999;
    //         } elseif ($currentDays > $maxDays) {
    //             $performanceLevel = '6. Deadstock (Mati)';
    //             $perfKey = 'deadstock';
    //             $gap = $bestDays !== null ? ($currentDays - $bestDays) : 9999;
    //         } elseif ($bestDays !== null) {
    //             $gap = $currentDays - $bestDays;
    //             $gapScore = max(0.0, min(1.0, 1 - ($gap / $maxGap)));
    //             $saleScore = max(0.0, min(1.0, 1 - ($currentDays / $maxDays)));

    //             // Bobot Baru: 20% Gap, 80% Sale History (Memprioritaskan kecepatan jual saat ini)
    //             $finalScore = ($gapScore * 0.2) + ($saleScore * 0.8);

    //             if ($finalScore >= 0.90) { $performanceLevel = '1. Elite (Terbaik)'; $perfKey = 'elite'; }
    //             elseif ($finalScore >= 0.70) { $performanceLevel = '2. Good (Aktif)'; $perfKey = 'good'; }
    //             elseif ($finalScore >= 0.50) { $performanceLevel = '3. Active (Normal)'; $perfKey = 'active'; }
    //             elseif ($finalScore >= 0.30) { $performanceLevel = '4. Lagging (Lambat)'; $perfKey = 'lagging'; }
    //             else { $performanceLevel = '5. Stagnant (Sangat Lambat)'; $perfKey = 'stagnant'; }
    //         }

    //         return [
    //             'item_id' => $item->item_id,
    //             'item_name' => $item->item_name,
    //             'score' => round($finalScore, 4),
    //             'performance_key' => $perfKey,
    //             'performance_level' => $performanceLevel,
    //             'gap_days' => $item->last_date_sale ? $gap : 'NEVER SOLD',
    //             'current_warehouse' => [
    //                 'name' => $item->warehouse_name,
    //                 'qty' => (int) $item->current_qty,
    //                 'last_sale' => $item->last_date_sale ?? 'NEVER SOLD',
    //                 'days_ago' => $item->last_date_sale ? $currentDays : 'NEVER SOLD',
    //             ],
    //             'best_performing_warehouse' => $bestSale ? [
    //                 'name' => $bestSale->warehouse_name,
    //                 'last_sale' => $bestSale->date,
    //                 'days_ago' => $bestDays,
    //                 'qty' => $bestWarehouseQty,
    //             ] : null,
    //             'audit_reference_date' => $systemLatestDate,
    //         ];
    //     });

    //     // Hitung Statistik (Stats)
    //     $stats = [
    //         'all' => $data->count(),
    //         'elite' => $data->where('performance_key', 'elite')->count(),
    //         'good' => $data->where('performance_key', 'good')->count(),
    //         'active' => $data->where('performance_key', 'active')->count(),
    //         'lagging' => $data->where('performance_key', 'lagging')->count(),
    //         'stagnant' => $data->where('performance_key', 'stagnant')->count(),
    //         'deadstock' => $data->where('performance_key', 'deadstock')->count(),
    //         'critical' => $data->where('performance_key', 'critical')->count(),
    //     ];

    //     if ($performanceFilter && $performanceFilter !== 'all') {
    //         $data = $data->where('performance_key', $performanceFilter);
    //     }

    //     if ($daysFilter) {
    //         $data = $data->where('current_warehouse.days_ago', '<=', (int) $daysFilter);
    //     }

    //     // Paginasi manual untuk collection
    //     $page = $request->input('page', 1);
    //     $perPage = 50;
    //     $items = $data->sortByDesc('score')->values();

    //     $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
    //         $items->forPage($page, $perPage),
    //         $items->count(),
    //         $perPage,
    //         $page,
    //         ['path' => $request->url(), 'query' => $request->query()]
    //     );

    //     return Inertia::render('Reports/StockIntelligence', [
    //         'data' => $paginatedData,
    //         'stats' => $stats,
    //         'filters' => $request->only(['days', 'performance', 'search']),
    //     ]);
    // }

    public function stockIntelligence(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $sellType = Transaction::TYPE_SELL;
        $perfFilter = $request->query('performance');
        $daysFilter = $request->query('days');
        $search = $request->query('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        // 1. Load Settings from Cache
        $settings = cache()->get('stock_intelligence_settings', [
            'gap_weight' => 0.2,
            'sale_weight' => 0.8,
            'max_gap' => 90,
            'max_days' => 90,
        ]);

        $systemLatestDate = DB::table('transaction_details')
            ->where('transaction_type', $sellType)
            ->max('date') ?? now()->toDateString();

        // ─────────────────────────────────────────────────────────────────
        // BASE QUERY — semua JOIN dikerjakan sekali
        // ─────────────────────────────────────────────────────────────────
        $baseQuery = DB::table('warehouse_items as wi')
            ->join('items as i', 'wi.item_id', '=', 'i.id')
            ->join('addrbooks as a', 'wi.warehouse_id', '=', 'a.id')

            // [ls] Last sale date per (item, warehouse) — aggregate, bukan correlated
            ->leftJoin(DB::raw('(
            SELECT item_id, sender_id, MAX(date) AS last_sale_date
            FROM   transaction_details
            WHERE  transaction_type = ?
            GROUP  BY item_id, sender_id
        ) ls'), function ($j) {
                $j->on('ls.item_id', '=', 'wi.item_id')
                    ->on('ls.sender_id', '=', 'wi.warehouse_id');
            })

            // [gb] Best global warehouse per item (tanggal penjualan terbaru)
            ->leftJoin(DB::raw('(
            SELECT
                td.item_id,
                td.sender_id                       AS best_warehouse_id,
                td.date                            AS best_sale_date,
                a2.name                            AS best_warehouse_name,
                DATEDIFF(CURDATE(), td.date)       AS best_days_ago
            FROM transaction_details td
            JOIN addrbooks a2 ON a2.id = td.sender_id
            WHERE td.transaction_type = ?
              AND (td.item_id, td.date, td.id) IN (
                    SELECT item_id, MAX(date), MAX(id)
                    FROM   transaction_details
                    WHERE  transaction_type = ?
                    GROUP  BY item_id
                  )
        ) gb'), 'gb.item_id', '=', 'wi.item_id')

            // [wibest] Qty di best warehouse
            ->leftJoin('warehouse_items as wibest', function ($j) {
                $j->on('wibest.item_id', '=', 'wi.item_id')
                    ->on('wibest.warehouse_id', '=', 'gb.best_warehouse_id');
            })

            ->where('wi.quantity', '>', 0)
            ->where('a.type', Addrbook::TYPE_WAREHOUSE)

            ->select(
                'wi.item_id',
                'i.name                          as item_name',
                'wi.warehouse_id',
                'a.name                          as warehouse_name',
                'wi.quantity                     as current_qty',
                'ls.last_sale_date',
                DB::raw('CASE WHEN ls.last_sale_date IS NOT NULL
                         THEN DATEDIFF(CURDATE(), ls.last_sale_date)
                         ELSE NULL END                               AS days_ago'),
                'gb.best_warehouse_id',
                'gb.best_warehouse_name',
                'gb.best_sale_date',
                'gb.best_days_ago',
                DB::raw('COALESCE(wibest.quantity, 0)                   AS best_warehouse_qty')
            )
            // Binding untuk 3 placeholder ? di DB::raw() JOIN di atas
            ->addBinding([$sellType, $sellType, $sellType], 'join');

        if ($search) {
            $baseQuery->where('i.name', 'like', "%{$search}%");
        }

        // ─────────────────────────────────────────────────────────────────
        // DERIVED SQL — dijadikan string untuk dibungkus query luar
        // ─────────────────────────────────────────────────────────────────
        $baseSql = $baseQuery->toSql();
        $baseBindings = $baseQuery->getBindings();

        // ─────────────────────────────────────────────────────────────────
        // SCORED LAYER — tambahkan kolom score & perf_key via CASE WHEN
        // Dua kolom ini dipakai oleh stats query DAN data query
        // ─────────────────────────────────────────────────────────────────
        $scoredSql = "
        SELECT
            base.*,
            CASE
                WHEN base.days_ago IS NULL      THEN 0
                WHEN base.days_ago > 90         THEN 0
                WHEN base.best_days_ago IS NOT NULL THEN
                    ROUND(
                          GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / 90.0)) * 0.2
                        + GREATEST(0, LEAST(1, 1 - base.days_ago / 90.0)) * 0.8
                    , 4)
                ELSE 0
            END AS score,
            CASE
                WHEN base.days_ago IS NULL THEN 'critical'
                WHEN base.days_ago > 90    THEN 'deadstock'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / 90.0)) * 0.2
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / 90.0)) * 0.8) >= 0.90 THEN 'elite'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / 90.0)) * 0.2
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / 90.0)) * 0.8) >= 0.70 THEN 'good'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / 90.0)) * 0.2
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / 90.0)) * 0.8) >= 0.50 THEN 'active'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / 90.0)) * 0.2
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / 90.0)) * 0.8) >= 0.30 THEN 'lagging'
                WHEN base.best_days_ago IS NOT NULL THEN 'stagnant'
                ELSE 'critical'
            END AS perf_key
        FROM ({$baseSql}) AS base
    ";
        // Bindings scored sama persis dengan base (tidak ada tambahan placeholder)
        $scoredBindings = $baseBindings;

        // ─────────────────────────────────────────────────────────────────
        // QUERY A — Stats: satu round-trip COUNT CASE WHEN
        // ─────────────────────────────────────────────────────────────────
        $statsRow = DB::table(DB::raw("({$scoredSql}) as scored"))
            ->addBinding($scoredBindings, 'join')
            ->selectRaw("
            COUNT(*)                                            AS total,
            SUM(perf_key = 'elite')                            AS cnt_elite,
            SUM(perf_key = 'good')                             AS cnt_good,
            SUM(perf_key = 'active')                           AS cnt_active,
            SUM(perf_key = 'lagging')                          AS cnt_lagging,
            SUM(perf_key = 'stagnant')                         AS cnt_stagnant,
            SUM(perf_key = 'deadstock')                        AS cnt_deadstock,
            SUM(perf_key = 'critical')                         AS cnt_critical
        ")
            ->first();

        $stats = [
            'all' => (int) $statsRow->total,
            'elite' => (int) $statsRow->cnt_elite,
            'good' => (int) $statsRow->cnt_good,
            'active' => (int) $statsRow->cnt_active,
            'lagging' => (int) $statsRow->cnt_lagging,
            'stagnant' => (int) $statsRow->cnt_stagnant,
            'deadstock' => (int) $statsRow->cnt_deadstock,
            'critical' => (int) $statsRow->cnt_critical,
        ];

        // ─────────────────────────────────────────────────────────────────
        // QUERY B — Data dengan filter, sort, paginate semuanya di SQL
        // ─────────────────────────────────────────────────────────────────
        $dataQuery = DB::table(DB::raw("({$scoredSql}) as scored"))
            ->addBinding($scoredBindings, 'join')
            ->select('*');

        if ($perfFilter && $perfFilter !== 'all') {
            $dataQuery->where('perf_key', $perfFilter);
        }

        if ($daysFilter) {
            $dataQuery->where('days_ago', '<=', (int) $daysFilter);
        }

        $totalFiltered = (clone $dataQuery)->count();

        $rows = $dataQuery
            ->orderByDesc('score')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // ─────────────────────────────────────────────────────────────────
        // MAP — pure transformation, zero DB call, zero Carbon
        // ─────────────────────────────────────────────────────────────────
        $perfLabels = [
            'elite' => '1. Elite (Terbaik)',
            'good' => '2. Good (Aktif)',
            'active' => '3. Active (Normal)',
            'lagging' => '4. Lagging (Lambat)',
            'stagnant' => '5. Stagnant (Sangat Lambat)',
            'deadstock' => '6. Deadstock (Mati)',
            'critical' => '7. Critical (Belum Terjual)',
        ];

        $mapped = $rows->map(function ($r) use ($systemLatestDate, $perfLabels) {
            $gapDays = match (true) {
                $r->last_sale_date === null => 'NEVER SOLD',
                $r->best_days_ago !== null => (int) $r->days_ago - (int) $r->best_days_ago,
                default => 9999,
            };

            return [
                'item_id' => $r->item_id,
                'item_name' => $r->item_name,
                'score' => (float) $r->score,
                'performance_key' => $r->perf_key,
                'performance_level' => $perfLabels[$r->perf_key] ?? $r->perf_key,
                'gap_days' => $gapDays,
                'current_warehouse' => [
                    'name' => $r->warehouse_name,
                    'qty' => (int) $r->current_qty,
                    'last_sale' => $r->last_sale_date ?? 'NEVER SOLD',
                    'days_ago' => $r->days_ago ?? 'NEVER SOLD',
                ],
                'best_performing_warehouse' => $r->best_warehouse_id ? [
                    'name' => $r->best_warehouse_name,
                    'last_sale' => $r->best_sale_date,
                    'days_ago' => (int) $r->best_days_ago,
                    'qty' => (int) $r->best_warehouse_qty,
                ] : null,
                'audit_reference_date' => $systemLatestDate,
            ];
        });

        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $mapped,
            $totalFiltered,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Reports/StockIntelligence', [
            'data' => $paginatedData,
            'stats' => $stats,
            'settings' => $settings,
            'filters' => $request->only(['days', 'performance', 'search']),
        ]);
    }

    public function rebalanceDetail(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        $itemId = $request->input('item_id');
        $sourceWhId = $request->input('warehouse_id');

        $item = Item::findOrFail($itemId);
        $sourceWh = Addrbook::findOrFail($sourceWhId);

        $date30 = now()->subDays(30)->toDateString();

        // 1. Ambil list semua gudang yang punya item ini
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

        // 2. Hitung Rekomendasi
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
}
