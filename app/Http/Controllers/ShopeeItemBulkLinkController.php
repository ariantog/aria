<?php

namespace App\Http\Controllers;

use App\Models\ShopeeBulkLinkRun;
use App\Models\ShopeeStock;
use App\Support\BulkLinkFailedRowsCsv;
use App\Services\Shopee\ShopeeItemBulkLinkService;
use App\Services\Shopee\ShopeeStockApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopeeItemBulkLinkController extends Controller
{
    public function index(ShopeeStockApiService $stockApi, ShopeeItemBulkLinkService $service): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $runningRun = $service->activeRun();
        $displayRunModel = $service->displayRun();
        $displayRun = $displayRunModel !== null ? $service->runForDisplay($displayRunModel) : null;

        $previewToken = (string) session('shopee_bulk_link_preview_token', '');
        $preview = $previewToken !== '' ? $service->previewForToken($previewToken) : null;
        if ($preview === null && $previewToken !== '') {
            session()->forget('shopee_bulk_link_preview_token');
        }

        $resultRows = array_merge(
            $preview['rows'] ?? [],
            $displayRun['failed_rows'] ?? [],
        );

        return view('shopee.bulk-link.index', [
            'stockReady' => $stockApi->isReady(),
            'preview' => $preview,
            'displayRun' => $displayRun,
            'bulkLinkRunning' => $runningRun !== null,
            'itemModels' => $service->loadItemsForResultRows($resultRows),
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

        session(['shopee_bulk_link_preview_token' => $result['token']]);

        return redirect()->route('shopee.bulk-link.index');
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

        session()->forget('shopee_bulk_link_preview_token');

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

    public function downloadErrors(ShopeeBulkLinkRun $run, ShopeeItemBulkLinkService $service): StreamedResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $rows = $service->storedFailedResults($run);
        $storedCount = count($rows);
        $suffix = $run->error_count > $storedCount
            ? sprintf('-errors-total%d-stored%d', $run->error_count, $storedCount)
            : sprintf('-errors-%d', $run->error_count);

        $filename = sprintf('shopee-bulk-link-run-%d%s.csv', $run->id, $suffix);

        return BulkLinkFailedRowsCsv::download(
            $filename,
            ['line', 'sku', 'shopee_item_id', 'shopee_model_id', 'status', 'message', 'aria_item_id'],
            $rows,
            (int) $run->error_count,
            function (array $row): array {
                $item = $row['item'] ?? null;

                return [
                    $row['line'] ?? '',
                    $row['code'] ?? ($item['code'] ?? ''),
                    $row['shopee_item_id'] ?? '',
                    $row['shopee_model_id'] ?? '',
                    $row['status'] ?? '',
                    $row['message'] ?? '',
                    is_array($item) ? ($item['id'] ?? '') : ($row['aria_item_id'] ?? ''),
                ];
            },
        );
    }

    public function dismissErrors(ShopeeBulkLinkRun $run, ShopeeItemBulkLinkService $service): RedirectResponse
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $display = $service->displayRun();
        if ($display === null || (int) $display->id !== (int) $run->id) {
            return back()->with('error', 'Run tidak aktif di halaman ini.');
        }

        $service->dismissErrorDisplay($run);

        return redirect()
            ->route('shopee.bulk-link.index')
            ->with('success', 'Laporan baris gagal disembunyikan — statistik error tetap di panel run.');
    }
}
