<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WarehouseItemReportController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_inventory_health']);

        // 1. AGGREGATE warehouse_items
        $wi = DB::table('warehouse_items')
            ->select(
                'warehouse_id',
                'item_id',
                DB::raw('SUM(quantity) as qty')
            )
            ->groupBy('warehouse_id', 'item_id');

        // 2. SUMMARY per warehouse
        $data = DB::table('addrbooks as c')
            ->leftJoinSub($wi, 'wi', function ($join) {
                $join->on('wi.warehouse_id', '=', 'c.id');
            })
            ->leftJoin('items as i', 'i.id', '=', 'wi.item_id')
            ->where('c.type', Addrbook::TYPE_WAREHOUSE)
            ->whereNull('c.deleted_at')
            ->select(
                'c.id',
                'c.name as nama_gudang',
                DB::raw('COUNT(DISTINCT wi.item_id) as total_item'),
                DB::raw('COALESCE(SUM(wi.qty), 0) as total_qty'),
                DB::raw("
                    COALESCE(SUM(
                        wi.qty * 
                        CASE 
                            WHEN i.type = 2 THEN COALESCE(i.cost, 0)
                            WHEN i.type = 1 THEN (COALESCE(i.price, 0) * 0.3)
                            ELSE 0
                        END
                    ), 0) as total_cost
                ")
            )
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.name')
            ->get();

        $totalWarehouse = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)->count();

        return Inertia::render('Reports/WarehouseItemReport', [
            'data' => $data,
            'totalWarehouse' => $totalWarehouse,
        ]);
    }
}
