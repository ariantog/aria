<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJubelioStockCheckRequest;
use App\Models\Jubelio;
use App\Models\JubelioStockCheck;
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
            'page_tracking' => $request->page_tracking,
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

        return view('jubelio.stock-check.show', [
            'stockCheck' => $jubelioStockCheck->load('discrepancies.warehouse', 'discrepancies.item'),
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
