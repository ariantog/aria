<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJubelioStockCheckRequest;
use App\Models\Jubelio;
use App\Models\JubelioStockCheck;
use App\Services\JubelioStockCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioStockCheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        Gate::authorize(Jubelio::getPermissions()['stock-check']);

        return view('jubelio.stock-check.index', [
            'stockChecks' => JubelioStockCheck::withCount('discrepancies')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
            'syncedWarehouseCount' => \App\Models\Jubeliosync::where('warehouse_id', '>', 0)->where('jubelio_location_id', '>', 0)->count(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        Gate::authorize(Jubelio::getPermissions()['stock-check']);

        return view('jubelio.stock-check.create', [
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJubelioStockCheckRequest $request): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['stock-check']);

        // Check if there is already an active job
        $activeJob = JubelioStockCheck::whereIn('status', ['created', 'processing'])->first();

        if ($activeJob) {
            return back()->withErrors(['active_job' => 'Terdapat pengecekan yang sedang berjalan atau menunggu diproses.']);
        }

        JubelioStockCheck::create([
            'sync_cursor' => 0,
            'per_type_limit' => $request->integer('per_type_limit'),
            'demand_days' => $request->integer('demand_days'),
            'target_discrepancies' => $request->integer('target_discrepancies', JubelioStockCheckService::DEFAULT_TARGET_DISCREPANCIES),
            'scan_round' => 0,
            'status' => 'created',
        ]);

        return redirect()->route('jubelio-stock-checks.index')
            ->with('success', 'Pengecekan stok berhasil dibuat dan akan segera diproses.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JubelioStockCheck $jubelioStockCheck): View
    {
        Gate::authorize(Jubelio::getPermissions()['stock-check']);

        $sort = request()->query('sort', 'abs_diff_desc');
        $discrepanciesQuery = $jubelioStockCheck->discrepancies()->with('warehouse', 'item');

        match ($sort) {
            'diff_asc' => $discrepanciesQuery->orderByRaw('(aria_qty - jubelio_qty) ASC'),
            'diff_desc' => $discrepanciesQuery->orderByRaw('(aria_qty - jubelio_qty) DESC'),
            default => $discrepanciesQuery->orderByRaw('ABS(aria_qty - jubelio_qty) DESC'),
        };

        return view('jubelio.stock-check.show', [
            'stockCheck' => $jubelioStockCheck,
            'discrepancies' => $discrepanciesQuery->get(),
            'sort' => $sort,
            'syncedWarehouseCount' => \App\Models\Jubeliosync::where('warehouse_id', '>', 0)->where('jubelio_location_id', '>', 0)->count(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JubelioStockCheck $jubelioStockCheck): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['stock-check']);

        $jubelioStockCheck->delete();

        return redirect()->route('jubelio-stock-checks.index')
            ->with('success', 'Data pengecekan berhasil dihapus.');
    }
}
