<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJubelioStockCheckRequest;
use App\Models\JubelioStockCheck;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JubelioStockCheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('jubelio/StockCheck/Index', [
            'stockChecks' => JubelioStockCheck::withCount('discrepancies')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('jubelio/StockCheck/Create', [
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJubelioStockCheckRequest $request): RedirectResponse
    {
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
    public function show(JubelioStockCheck $jubelioStockCheck): Response
    {
        return Inertia::render('jubelio/StockCheck/Show', [
            'stockCheck' => $jubelioStockCheck->load('discrepancies.warehouse', 'discrepancies.item'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JubelioStockCheck $jubelioStockCheck): RedirectResponse
    {
        $jubelioStockCheck->delete();

        return redirect()->route('jubelio-stock-checks.index')
            ->with('success', 'Data pengecekan berhasil dihapus.');
    }
}
