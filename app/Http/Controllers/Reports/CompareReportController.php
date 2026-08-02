<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Models\WarehouseCompare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompareReportController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-compare']);

        // 1. Get warehouses the user is currently comparing
        $selectedWarehouses = WarehouseCompare::where('user_id', Auth::id())
            ->with('warehouse:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        $warehouseIds = $selectedWarehouses->pluck('warehouse_id')->toArray();

        // 2. Query products and their stock if warehouses are selected
        $items = [
            'data' => [],
            'links' => [],
            'meta' => [
                'current_page' => 1,
                'from' => 0,
                'last_page' => 1,
                'path' => '',
                'per_page' => 50,
                'to' => 0,
                'total' => 0,
            ],
        ];

        if (! empty($warehouseIds)) {
            $query = Item::query()
                ->join('warehouse_items', 'items.id', '=', 'warehouse_items.item_id')
                ->whereIn('warehouse_items.warehouse_id', $warehouseIds)
                ->select(
                    'items.id',
                    'items.name',
                    'items.code'
                )
                ->groupBy('items.id', 'items.name', 'items.code');

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('items.name', 'like', "%{$request->search}%")
                        ->orWhere('items.code', 'like', "%{$request->search}%");
                });
            }

            // Sorting logic
            $sort = $request->input('sort', 'name');
            $direction = $request->input('direction', 'asc');

            if (str_starts_with($sort, 'wh_')) {
                $whId = str_replace('wh_', '', $sort);
                $query->orderByRaw("(SELECT quantity FROM warehouse_items WHERE item_id = items.id AND warehouse_id = ?) $direction", [$whId]);
            } else {
                $query->orderBy("items.$sort", $direction);
            }

            $paginator = $query->paginate(50)->withQueryString();

            // 3. Load specific stock levels for each selected warehouse
            $itemIds = collect($paginator->items())->pluck('id')->toArray();
            $stockDetails = DB::table('warehouse_items')
                ->whereIn('warehouse_id', $warehouseIds)
                ->whereIn('item_id', $itemIds)
                ->get()
                ->groupBy('item_id');

            $mappedItems = collect($paginator->items())->map(function ($item) use ($stockDetails, $warehouseIds) {
                $itemData = $item->toArray();
                foreach ($warehouseIds as $whId) {
                    $stockRecord = $stockDetails->get($item->id)?->firstWhere('warehouse_id', $whId);
                    $itemData["wh_$whId"] = $stockRecord ? (float) $stockRecord->quantity : 0;
                }

                return $itemData;
            });

            $items = [
                'data' => $mappedItems,
                'links' => $paginator->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'path' => $paginator->path(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        // 4. Get all warehouses for selection
        $allWarehouses = Addrbook::where('type', Addrbook::TYPE_WAREHOUSE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.compare', [
            'items' => $items,
            'selectedWarehouses' => $selectedWarehouses,
            'allWarehouses' => $allWarehouses,
            'filters' => [
                'search' => $request->search ?? '',
                'sort' => $request->sort ?? 'name',
                'direction' => $request->direction ?? 'asc',
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-compare']);

        $request->validate([
            'warehouse_id' => [
                'required',
                Rule::unique('warehouse_compares', 'warehouse_id')->where(function ($query) {
                    return $query->where('user_id', Auth::id());
                }),
            ],
        ]);

        WarehouseCompare::create([
            'user_id' => Auth::id(),
            'warehouse_id' => $request->warehouse_id,
        ]);

        return back()->with('success', 'Gudang berhasil ditambahkan ke perbandingan.');
    }

    public function destroy(WarehouseCompare $compare)
    {
        Gate::authorize(Report::getPermissions()['view-compare']);

        if ($compare->user_id !== Auth::id()) {
            abort(403);
        }

        $compare->delete();

        return back()->with('success', 'Gudang berhasil dihapus dari perbandingan.');
    }
}
