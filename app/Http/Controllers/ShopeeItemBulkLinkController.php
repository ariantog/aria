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
    public function index(ShopeeStockApiService $stockApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        return view('shopee.bulk-link.index', [
            'stockReady' => $stockApi->isReady(),
            'preview' => session('preview'),
            'applyResult' => session('apply_result'),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function preview(Request $request, ShopeeItemBulkLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
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
            $result = $service->apply(
                $validated['token'],
                (bool) ($validated['overwrite_existing'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shopee.bulk-link.index')
            ->with('success', sprintf(
                'Bulk link selesai: %d linked, %d skipped, %d error dari %d baris.',
                $result['summary']['linked'] ?? 0,
                $result['summary']['skipped'],
                $result['summary']['errors'],
                $result['summary']['total'],
            ))
            ->with('apply_result', $result);
    }
}
