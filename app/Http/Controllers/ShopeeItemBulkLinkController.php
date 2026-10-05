<?php

namespace App\Http\Controllers;

use App\Models\ShopeeStock;
use App\Services\Shopee\ShopeeItemBulkLinkService;
use App\Services\Shopee\ShopeeStockApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopeeItemBulkLinkController extends Controller
{
    public function index(ShopeeStockApiService $stockApi, ShopeeItemBulkLinkService $service): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $activeRun = $service->activeRun();
        $activeRunDisplay = $activeRun !== null ? $service->runForDisplay($activeRun) : null;

        return view('shopee.bulk-link.index', [
            'stockReady' => $stockApi->isReady(),
            'preview' => session('preview'),
            'activeRun' => $activeRunDisplay,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function preview(Request $request, ShopeeItemBulkLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        try {
            $result = $service->preview($validated['file']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shopee.bulk-link.index')
            ->with('preview', $result);
    }

    public function apply(Request $request, ShopeeItemBulkLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $run = $service->startApply(
                $validated['token'],
                (bool) ($validated['overwrite_existing'] ?? false),
                (int) ($request->user()?->id ?? 0),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $remaining = max(0, $run->total_rows - $run->processed_rows);
        $batchesLeft = (int) ceil($remaining / ShopeeItemBulkLinkService::BATCH_SIZE);

        return redirect()
            ->route('shopee.bulk-link.index')
            ->with('success', sprintf(
                'Bulk link dimulai: batch pertama selesai (%d / %d baris). ~%d batch berikutnya jalan otomatis (max %d baris/menit).',
                $run->processed_rows,
                $run->total_rows,
                $batchesLeft,
                ShopeeItemBulkLinkService::BATCH_SIZE,
            ));
    }
}
