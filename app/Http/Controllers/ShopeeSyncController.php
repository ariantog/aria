<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\ShopeeStock;
use App\Models\Shopeesync;
use App\Services\Shopee\ShopeeStockApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeSyncController extends Controller
{
    public function __construct(private ShopeeStockApiService $stockApi) {}

    public function index(Request $request): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $query = Shopeesync::with(['warehouse']);

        if ($request->query('name')) {
            $name = str_replace(' ', '%', (string) $request->query('name'));
            $query->where(function ($q) use ($name) {
                $q->where('shopee_warehouse_name', 'LIKE', "%{$name}%")
                    ->orWhere('shopee_location_id', 'LIKE', "%{$name}%");
            });
        }

        $dataList = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('shopee.sync.index', [
            'dataList' => $dataList,
            'filters' => $request->only(['name']),
            'connection' => [
                'ready' => $this->stockApi->isReady(),
            ],
            'flash' => ['success' => session('success'), 'error' => session('fail') ?? session('errorMessage') ?? session('error')],
        ]);
    }

    public function create(): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $warehouses = $this->stockApi->isReady()
            ? $this->stockApi->listPickupWarehouses()
            : [];

        return view('shopee.sync.create', [
            'shopeeWarehouses' => $warehouses,
            'connectionReady' => $this->stockApi->isReady(),
            'addrbookTypes' => [
                'warehouse' => Addrbook::TYPE_WAREHOUSE,
            ],
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $request->validate([
            'warehouse_id' => 'required|exists:customers,id',
            'shopee_warehouse_id' => 'required|integer|min:1',
            'shopee_location_id' => 'nullable|string|max:32',
            'shopee_warehouse_name' => 'required|string|max:255',
        ]);

        Shopeesync::create([
            'warehouse_id' => (int) $request->warehouse_id,
            'shop_id' => 0,
            'shopee_location_id' => (string) ($request->shopee_location_id ?? ''),
            'shopee_warehouse_id' => (int) $request->shopee_warehouse_id,
            'shopee_warehouse_name' => (string) $request->shopee_warehouse_name,
        ]);

        return redirect()->route('shopee.sync.index')->with('success', 'Shopee warehouse mapping created.');
    }

    public function edit(Shopeesync $sync): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return view('shopee.sync.edit', [
            'sync' => $sync->load(['warehouse']),
            'addrbookTypes' => [
                'warehouse' => Addrbook::TYPE_WAREHOUSE,
            ],
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    public function update(Request $request, Shopeesync $sync): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $request->validate([
            'warehouse_id' => 'required|exists:customers,id',
        ]);

        $sync->update([
            'warehouse_id' => (int) $request->warehouse_id,
        ]);

        return redirect()->route('shopee.sync.index')->with('success', 'Shopee warehouse mapping updated.');
    }

    public function destroy(Shopeesync $sync): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $sync->delete();

        return redirect()->route('shopee.sync.index')->with('success', 'Mapping deleted.');
    }
}
