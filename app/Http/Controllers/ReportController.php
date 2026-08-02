<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
        $query->selectRaw('(SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_30, (SELECT SUM(td.quantity) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? AND td.date>=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as sold_90, (SELECT MAX(td.date) FROM transaction_details td WHERE td.item_id=items.id AND td.transaction_type=? '.($warehouseId ? "AND td.sender_id=$warehouseId" : '').') as last_sold_at, (SELECT SUM(quantity) FROM warehouse_items WHERE item_id=items.id '.($warehouseId ? "AND warehouse_id=$warehouseId" : '').') as current_stock', [Transaction::TYPE_SELL, $date30, Transaction::TYPE_SELL, $date90, Transaction::TYPE_SELL]);
        $items = $query->paginate(50)->withQueryString();
        $warehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->orderBy('name')->get();

        return view('reports.inventory-health', ['items' => $items, 'warehouses' => $warehouses, 'filters' => $request->only(['warehouse_id', 'search']), 'flash' => ['success' => session('success'), 'error' => session('error')]]);
    }

    public function stockIntelligence(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-inventory-health']);

        $reportId = $request->query('report_id');
        $reportHistory = \App\Models\StokReport::latest('generet_at')
            ->limit(5)
            ->get(['id', 'generet_at', 'type'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->generet_at->locale('id')->translatedFormat('d M Y H:i')." ({$r->type})",
            ]);

        $latestReport = $reportId ? \App\Models\StokReport::find($reportId) : \App\Models\StokReport::latest('generet_at')->first();

        $data = ['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0, 'links' => []];
        $stats = [
            'all' => 0, 'elite' => 0, 'good' => 0, 'active' => 0,
            'lagging' => 0, 'stagnant' => 0, 'deadstock' => 0, 'critical' => 0,
        ];
        $reportInfo = null;

        if ($latestReport) {
            $dataQuery = \App\Models\StockData::where('id_stock_report', $latestReport->id);

            // Calculate stats for tabs
            $statsRaw = \App\Models\StockData::where('id_stock_report', $latestReport->id)
                ->selectRaw('performance_key, count(*) as total')
                ->groupBy('performance_key')
                ->pluck('total', 'performance_key')
                ->toArray();

            foreach ($statsRaw as $key => $total) {
                if (isset($stats[$key])) {
                    $stats[$key] = $total;
                }
            }
            $stats['all'] = array_sum($statsRaw);

            if ($request->search) {
                $dataQuery->where(fn ($q) => $q->where('item_name', 'like', "%{$request->search}%")->orWhere('item_id', $request->search));
            }

            if ($request->performance && $request->performance !== 'all') {
                $dataQuery->where('performance_key', $request->performance);
            }

            $data = $dataQuery->orderByDesc('score')->paginate(50)->withQueryString();

            // Transform data for frontend
            $data->getCollection()->transform(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'item_name' => $row->item_name,
                    'performance_level' => $row->performance_level,
                    'performance_key' => $row->performance_key,
                    'score' => (float) $row->score,
                    'previous_score' => null, // Would need comparison logic
                    'gap_days' => $row->gap_days ?? 'NEVER SOLD',
                    'current_warehouse' => [
                        'id' => $row->current_warehouse_id,
                        'name' => $row->current_warehouse_name,
                        'qty' => $row->current_warehouse_qty,
                        'last_sale' => $row->current_warehouse_last_sale ?? '-',
                        'days_ago' => $row->current_warehouse_days_ago ?? 'NEVER SOLD',
                    ],
                    'best_performing_warehouse' => $row->best_performing_warehouse_id ? [
                        'id' => $row->best_performing_warehouse_id,
                        'name' => $row->best_performing_warehouse_name,
                        'last_sale' => $row->best_performing_warehouse_last_sale ?? '-',
                        'days_ago' => $row->best_performing_warehouse_days_ago,
                        'qty' => $row->best_performing_warehouse_qty,
                    ] : null,
                    'audit_reference_date' => $row->audit_reference_date,
                ];
            });

            $reportInfo = [
                'generet_at' => $latestReport->generet_at->locale('id')->translatedFormat('d F Y, H:i'),
                'type' => strtoupper($latestReport->type),
                'generet_by' => $latestReport->user?->name ?? 'System',
                'next_run' => null, // Could calculate based on settings
                'last_update_days_ago' => $latestReport->generet_at->diffForHumans(),
            ];
        }

        $settings = [
            'gap_weight' => (float) \App\Models\Setting::getValue('si_gap_weight', 0.2),
            'sale_weight' => (float) \App\Models\Setting::getValue('si_sale_weight', 0.8),
            'max_gap' => (int) \App\Models\Setting::getValue('si_max_gap', 90),
            'max_days' => (int) \App\Models\Setting::getValue('si_max_days', 90),
            'total_rows' => (int) \App\Models\Setting::getValue('si_total_rows', 1000),
            'generate_days' => \App\Models\Setting::getValue('si_generate_days', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
        ];

        return view('reports.stock-intelligence', [
            'data' => $data,
            'stats' => $stats,
            'settings' => $settings,
            'reportInfo' => $reportInfo,
            'reportHistory' => $reportHistory,
            'currentReportId' => $latestReport?->id,
            'filters' => [
                'performance' => $request->query('performance', 'all'),
                'search' => $request->query('search', ''),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
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
        Gate::authorize(Report::getPermissions()['view-inventory-health']);

        return view('reports.rebalance-detail', [
            'item' => ['id' => (int) $request->query('item_id'), 'name' => 'Item #'.$request->query('item_id'), 'code' => ''],
            'sourceWarehouse' => ['id' => (int) $request->query('warehouse_id'), 'name' => ''],
            'warehouseStocks' => [],
            'recommendation' => null,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function generateManual()
    {
        Artisan::call('app:generate-stock-intelligence');

        return back()->with('success', 'Generated.');
    }
}
