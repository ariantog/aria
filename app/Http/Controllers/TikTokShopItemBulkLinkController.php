<?php

namespace App\Http\Controllers;

use App\Models\TikTokBulkLinkRun;
use App\Models\TikTokShop;
use App\Support\BulkLinkFailedRowsCsv;
use App\Services\TikTokShop\TikTokShopItemBulkLinkService;
use App\Services\TikTokShop\TikTokShopItemSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TikTokShopItemBulkLinkController extends Controller
{
    public function index(
        TikTokShopItemSearchService $searchService,
        TikTokShopItemBulkLinkService $service,
    ): View {
        $this->authorizeBulkLink();

        $runningRun = $service->activeRun();
        $displayRunModel = $service->displayRun();
        $displayRun = $displayRunModel !== null ? $service->runForDisplay($displayRunModel) : null;

        $previewToken = (string) session('tiktok_bulk_link_preview_token', '');
        $preview = $previewToken !== '' ? $service->previewForToken($previewToken) : null;
        if ($preview === null && $previewToken !== '') {
            session()->forget('tiktok_bulk_link_preview_token');
        }

        $resultRows = array_merge(
            $preview['rows'] ?? [],
            $displayRun['failed_rows'] ?? [],
        );

        return view('tiktok-shop.bulk-link.index', [
            'oauthReady' => $searchService->isReady(),
            'preview' => $preview,
            'displayRun' => $displayRun,
            'bulkLinkRunning' => $runningRun !== null,
            'itemModels' => $service->loadItemsForResultRows($resultRows),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function preview(Request $request, TikTokShopItemBulkLinkService $service): RedirectResponse
    {
        $this->authorizeBulkLink();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        try {
            $result = $service->preview($validated['file']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        session(['tiktok_bulk_link_preview_token' => $result['token']]);

        return redirect()->route('tiktok-shop.bulk-link.index');
    }

    public function apply(Request $request, TikTokShopItemBulkLinkService $service): RedirectResponse
    {
        $this->authorizeBulkLink();

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

        session()->forget('tiktok_bulk_link_preview_token');

        $remaining = max(0, $run->total_rows - $run->processed_rows);
        $batchesLeft = (int) ceil($remaining / TikTokShopItemBulkLinkService::BATCH_SIZE);

        return redirect()
            ->route('tiktok-shop.bulk-link.index')
            ->with('success', sprintf(
                'Bulk link dimulai: batch pertama selesai (%d / %d baris). ~%d batch berikutnya jalan otomatis (max %d baris/menit).',
                $run->processed_rows,
                $run->total_rows,
                $batchesLeft,
                TikTokShopItemBulkLinkService::BATCH_SIZE,
            ));
    }

    public function downloadErrors(TikTokBulkLinkRun $run, TikTokShopItemBulkLinkService $service): StreamedResponse
    {
        $this->authorizeBulkLink();

        $rows = $service->storedFailedResults($run);
        $storedCount = count($rows);
        $suffix = $run->error_count > $storedCount
            ? sprintf('-errors-total%d-stored%d', $run->error_count, $storedCount)
            : sprintf('-errors-%d', $run->error_count);

        $filename = sprintf('tiktok-bulk-link-run-%d%s.csv', $run->id, $suffix);

        return BulkLinkFailedRowsCsv::download(
            $filename,
            ['line', 'sku', 'tiktok_product_id', 'tiktok_sku_id', 'status', 'message', 'aria_item_id'],
            $rows,
            (int) $run->error_count,
            function (array $row): array {
                $item = $row['item'] ?? null;

                return [
                    $row['line'] ?? '',
                    $row['code'] ?? ($item['code'] ?? ''),
                    $row['tiktok_product_id'] ?? '',
                    $row['tiktok_sku_id'] ?? '',
                    $row['status'] ?? '',
                    $row['message'] ?? '',
                    is_array($item) ? ($item['id'] ?? '') : ($row['aria_item_id'] ?? ''),
                ];
            },
        );
    }

    public function dismissErrors(TikTokBulkLinkRun $run, TikTokShopItemBulkLinkService $service): RedirectResponse
    {
        $this->authorizeBulkLink();

        $display = $service->displayRun();
        if ($display === null || (int) $display->id !== (int) $run->id) {
            return back()->with('error', 'Run tidak aktif di halaman ini.');
        }

        $service->dismissErrorDisplay($run);

        return redirect()
            ->route('tiktok-shop.bulk-link.index')
            ->with('success', 'Laporan baris gagal disembunyikan — statistik error tetap di panel run.');
    }

    private function authorizeBulkLink(): void
    {
        if (Gate::any([
            TikTokShop::getPermissions()['item_link'],
            TikTokShop::getPermissions()['view'],
        ])) {
            return;
        }

        abort(403);
    }
}