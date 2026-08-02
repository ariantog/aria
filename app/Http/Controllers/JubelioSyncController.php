<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Jubelio;
use App\Models\Jubeliosync;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class JubelioSyncController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $query = Jubeliosync::with(['warehouse', 'customer']);

        if ($request->name) {
            $name = str_replace(' ', '%', $request->name);
            $query->where('jubelio_location_name', 'LIKE', "%$name%");
        }

        $dataList = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        return view('jubelio.sync.index', [
            'dataList' => $dataList,
            'filters' => $request->only(['name']),
            'flash' => ['success' => session('success'), 'error' => session('fail') ?? session('errorMessage') ?? session('error')],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $token = Cache::get('jubelio_data')['token'] ?? null;

        $dataList = ['data' => []];
        if ($token) {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->get('https://api2.jubelio.com/locations/', [
                'page' => 1,
                'pageSize' => 200,
            ]);

            if ($response->successful()) {
                $dataList = $response->json();
            }
        }

        return view('jubelio.sync.create', [
            'locations' => $dataList['data'] ?? [],
            'addrbookTypes' => [
                'warehouse' => Addrbook::TYPE_WAREHOUSE,
                'customer' => Addrbook::TYPE_CUSTOMER,
            ],
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $request->validate([
            'location_id' => 'required',
            'location_name' => 'required',
            'warehouse_id' => 'required|exists:addrbooks,id',
        ]);

        $token = Cache::get('jubelio_data')['token'] ?? null;
        if (! $token) {
            return back()->with('errorMessage', 'Jubelio token not found in cache.');
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->get('https://api2.jubelio.com/locations/'.$request->location_id);

        if (! $response->successful()) {
            return back()->with('errorMessage', 'Failed to fetch location details from Jubelio.');
        }

        $dataList = $response->json();
        $dataRow = $dataList['warehouse'] ?? ($dataList['stores'] ?? []);

        if (empty($dataRow)) {
            return back()->with('errorMessage', 'No stores or warehouses found for this location in Jubelio.');
        }

        $syncArray = [];
        foreach ($dataRow as $data) {
            $syncArray[] = [
                'jubelio_store_id' => $data['store_id'],
                'jubelio_store_name' => $data['store_name'],
                'jubelio_location_id' => $request->location_id,
                'jubelio_location_name' => $request->location_name,
                'warehouse_id' => $request->warehouse_id,
                'customer_id' => $request->customer_id ?? 0,
                'bin_id' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('jubeliosyncs')->insert($syncArray);

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jubeliosync $sync): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        return view('jubelio.sync.edit', [
            'sync' => $sync->load(['warehouse', 'customer']),
            'addrbookTypes' => [
                'warehouse' => Addrbook::TYPE_WAREHOUSE,
                'customer' => Addrbook::TYPE_CUSTOMER,
            ],
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Jubeliosync $sync): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $request->validate([
            'warehouse_id' => 'required|exists:addrbooks,id',
            'customer_id' => 'nullable|exists:addrbooks,id',
        ]);

        $sync->update([
            'warehouse_id' => $request->warehouse_id,
            'customer_id' => $request->customer_id ?? 0,
        ]);

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jubeliosync $sync): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $sync->delete();

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync deleted.');
    }

    /**
     * Update bin ID from Jubelio.
     */
    public function getBin(Jubeliosync $sync): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $token = Cache::get('jubelio_data')['token'] ?? null;
        if (! $token) {
            return back()->with('errorMessage', 'Jubelio token not found.');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'authorization' => $token,
        ])->get('https://api2.jubelio.com/wms/default-bin/'.$sync->jubelio_location_id);

        if ($response->successful()) {
            $result = $response->json();
            $sync->update(['bin_id' => $result['bin_id'] ?? 0]);

            return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio updated bin id.');
        } else {
            $error = $response->json();
            $message = $error['message'] ?? 'Terjadi kesalahan saat mengambil bin.';

            return redirect()->route('jubelio.sync.index')->with('fail', $message);
        }
    }
}
