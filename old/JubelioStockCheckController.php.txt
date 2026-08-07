<?php

namespace App\Http\Controllers;

use App\Models\JubelioStockCheck;
use Illuminate\Http\Request;

class JubelioStockCheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('jubelio.StockCheck.index', [
            'stockChecks' => JubelioStockCheck::withCount('discrepancies')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('jubelio.StockCheck.create', [
            'activeJob' => JubelioStockCheck::whereIn('status', ['created', 'processing'])->first(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         // Check if there is already an active job
        $activeJob = JubelioStockCheck::whereIn('status', ['created', 'processing'])->first();

        if ($activeJob) {
            return back()->withErrors(['active_job' => 'Terdapat pengecekan yang sedang berjalan atau menunggu diproses.']);
        }

        JubelioStockCheck::create([
            'page_tracking' => $request->page_tracking,
            'status' => 'processing',
        ]);

        return redirect()->route('jubelio-stock-checks.index')
            ->with('success', 'Pengecekan stok berhasil dibuat dan akan segera diproses.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return view('jubelio.StockCheck.show', [
            'stockCheck' => JubelioStockCheck::findOrFail($id)->load('discrepancies.warehouse', 'discrepancies.item'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jubelioStockCheck = JubelioStockCheck::findOrFail($id);    
        $jubelioStockCheck->delete();

        return redirect()->route('jubelio-stock-checks.index')
            ->with('success', 'Data pengecekan berhasil dihapus.');
    }
}
