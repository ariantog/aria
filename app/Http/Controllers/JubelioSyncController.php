<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Jubelio;
use App\Models\Jubeliosync;
use App\Services\Jubelio\JubelioSyncCatalogService;
use App\Services\JubelioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioSyncController extends Controller
{
    public function __construct(
        private JubelioService $jubelioService,
        private JubelioSyncCatalogService $syncCatalog,
    ) {}

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

        $locations = $this->syncCatalog->fetchAllLocations();

        return view('jubelio.sync.create', [
            'locations' => $locations,
            'preselectedLocationId' => request()->query('location_id'),
            'preselectedStoreIds' => array_filter(array_map('intval', (array) request()->query('store_ids', []))),
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

        $validated = $request->validate([
            'location_id' => 'required|integer',
            'location_name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:customers,id',
            'customer_id' => 'nullable|exists:customers,id',
            'store_ids' => 'required|array|min:1',
            'store_ids.*' => 'integer|min:1',
        ]);

        $locationId = (int) $validated['location_id'];
        $selectedStoreIds = collect($validated['store_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $stores = $this->syncCatalog->fetchStoresForLocation($locationId);

        if ($stores === null) {
            return back()->withInput()->with('errorMessage', 'Gagal mengambil daftar marketplace dari Jubelio.');
        }

        if ($stores === []) {
            return back()->withInput()->with('errorMessage', 'Tidak ada marketplace di lokasi Jubelio ini.');
        }

        $storesById = collect($stores)->keyBy('store_id');
        $invalidIds = $selectedStoreIds->filter(fn (int $id) => ! $storesById->has($id));
        if ($invalidIds->isNotEmpty()) {
            return back()->withInput()->with('errorMessage', 'Marketplace tidak valid untuk lokasi ini: '.$invalidIds->implode(', '));
        }

        $mappedKeys = $this->syncCatalog->mappedStoreLocationKeys();
        $created = 0;
        $skipped = 0;

        foreach ($selectedStoreIds as $storeId) {
            $pairKey = $this->syncCatalog->pairKey($storeId, $locationId);
            if (isset($mappedKeys[$pairKey])) {
                $skipped++;

                continue;
            }

            $store = $storesById->get($storeId);

            Jubeliosync::create([
                'jubelio_store_id' => $storeId,
                'jubelio_store_name' => $store['store_name'],
                'jubelio_location_id' => $locationId,
                'jubelio_location_name' => $validated['location_name'],
                'warehouse_id' => (int) $validated['warehouse_id'],
                'customer_id' => (int) ($validated['customer_id'] ?? 0),
                'bin_id' => 0,
            ]);
            $created++;
        }

        if ($created === 0) {
            return redirect()->route('jubelio.sync.index')
                ->with('fail', 'Tidak ada mapping baru — marketplace yang dipilih sudah ada.');
        }

        $message = $created === 1
            ? '1 mapping marketplace dibuat.'
            : "{$created} mapping marketplace dibuat.";

        if ($skipped > 0) {
            $message .= " {$skipped} sudah ada dan dilewati.";
        }

        return redirect()->route('jubelio.sync.index')->with('success', $message);
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
            'warehouse_id' => 'required|exists:customers,id',
            'customer_id' => 'nullable|exists:customers,id',
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
     * List Jubelio store channels not yet mapped in Aria (for locations we already use).
     */
    public function discover(): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $unmapped = $this->syncCatalog->discoverUnmappedStores();

        return view('jubelio.sync.discover', [
            'unmapped' => $unmapped,
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    /**
     * JSON: stores at a Jubelio location, with mapped flag for the create form.
     */
    public function locationStores(int $locationId): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $stores = $this->syncCatalog->fetchStoresForLocation($locationId);

        if ($stores === null) {
            return response()->json(['message' => 'Gagal mengambil marketplace dari Jubelio.'], 502);
        }

        $mappedKeys = $this->syncCatalog->mappedStoreLocationKeys();

        $payload = collect($stores)->map(function (array $store) use ($locationId, $mappedKeys) {
            $key = $this->syncCatalog->pairKey($store['store_id'], $locationId);

            return [
                'store_id' => $store['store_id'],
                'store_name' => $store['store_name'],
                'mapped' => isset($mappedKeys[$key]),
            ];
        })->values();

        return response()->json(['stores' => $payload]);
    }

    /**
     * Update bin ID from Jubelio for one mapping row.
     */
    public function getBin(Jubeliosync $sync): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $result = $this->jubelioService->fetchDefaultBinId((int) $sync->jubelio_location_id);

        if (! $result['ok']) {
            return redirect()->route('jubelio.sync.index')
                ->with('fail', $result['message']);
        }

        $sync->update(['bin_id' => $result['bin_id']]);

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio updated bin id.');
    }

    /**
     * Fetch default bin IDs from Jubelio for every mapped location (deduped by location).
     */
    public function refreshAllBins(Request $request): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);

        $query = Jubeliosync::query();

        if ($request->name) {
            $name = str_replace(' ', '%', $request->name);
            $query->where('jubelio_location_name', 'LIKE', "%$name%");
        }

        $locationIds = $query
            ->pluck('jubelio_location_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => Jubeliosync::isMappedLocationId($id))
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return redirect()->route('jubelio.sync.index', $request->only('name'))
                ->with('errorMessage', 'Tidak ada mapping untuk dicek.');
        }

        $updatedRows = 0;
        $updatedLocations = 0;
        $failures = [];

        foreach ($locationIds as $locationId) {
            $result = $this->jubelioService->fetchDefaultBinId($locationId);

            if (! $result['ok']) {
                $failures[] = "Lokasi {$locationId}: {$result['message']}";

                continue;
            }

            $updatedRows += Jubeliosync::query()
                ->where('jubelio_location_id', $locationId)
                ->update([
                    'bin_id' => $result['bin_id'],
                    'updated_at' => now(),
                ]);
            $updatedLocations++;
        }

        $redirect = redirect()->route('jubelio.sync.index', $request->only('name'));

        if ($updatedLocations === 0) {
            return $redirect->with('fail', implode('; ', $failures));
        }

        $message = "Bin diperbarui untuk {$updatedRows} mapping ({$updatedLocations} lokasi Jubelio).";

        if ($failures !== []) {
            $message .= ' Gagal: '.count($failures).' lokasi — '.implode('; ', array_slice($failures, 0, 3));
            if (count($failures) > 3) {
                $message .= '…';
            }

            return $redirect->with('fail', $message);
        }

        return $redirect->with('success', $message);
    }

}
