<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\Omnichannel;
use App\Models\Transaction;
use App\Services\Marketplace\MarketplaceChannelOrderAwbDocumentState;
use App\Services\Marketplace\MarketplaceChannelOrderAwbPrintGuard;
use App\Services\Marketplace\MarketplaceChannelOrderAwbPromoter;
use App\Services\Marketplace\MarketplaceChannelOrderWarehousePersist;
use App\Services\Marketplace\MarketplaceChannelOrderOpsBucket;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use App\Services\Marketplace\OmnichannelOrderDetailReader;
use App\Services\Marketplace\OmnichannelPostSellDashboardStats;
use App\Services\Marketplace\OmnichannelPostSellEligibility;
use App\Models\MarketplaceMissedPushGap;
use App\Services\Marketplace\MarketplaceMissedPushGapQuery;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\ShopeeMissedPushReconcileService;
use App\Services\Shopee\ShopeeShippingDocumentService;
use App\Services\TikTokShop\TikTokShopShippingDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class OmnichannelOrdersController extends Controller
{
    public function index(
        Request $request,
        MarketplaceWebhookInboxQuery $inboxQuery,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
        MarketplaceWebhookStatusPresenter $statusPresenter,
        MarketplaceChannelOrderWarehousePersist $warehousePersist,
        MarketplaceChannelOrderOpsBucket $opsBucket,
        OmnichannelPostSellDashboardStats $postSellStats,
        OmnichannelPostSellEligibility $postSellEligibility,
    ): View|RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        if ($request->query('view') === 'missed_push') {
            $query = $request->query();
            unset($query['view']);

            return redirect()->route('omnichannel.missed-push.index', $query);
        }

        $filters = $inboxQuery->parseFilters($request);

        $eventsTableExists = Schema::hasTable('marketplace_webhook_events');
        $ordersTableExists = Schema::hasTable('marketplace_channel_orders');
        $rows = null;
        $statusOptions = [];
        $opsBucketStats = [];

        if ($filters['view'] === 'orders' && $ordersTableExists) {
            $opsBucketStats = $opsBucket->countBuckets($inboxQuery->ordersQueryForStats($filters));
            $rows = $inboxQuery->ordersQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
            if (Schema::hasColumn('marketplace_channel_orders', 'awb_printed_by')) {
                $rows->load('awbPrintedBy:id,username');
            }
            $statusOptions = $inboxQuery->distinctOrderStatuses($filters, 'orders');
        } elseif ($filters['view'] === 'events' && $eventsTableExists) {
            $rows = $inboxQuery->eventsQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
            $statusOptions = $inboxQuery->distinctOrderStatuses($filters, 'events');
        }

        $channelForShops = $filters['channel'] === 'all' ? null : $filters['channel'];
        if ($channelForShops === 'tokopedia') {
            $channelForShops = 'tiktok';
        }

        $warehouseOptions = $shopResolver->warehouseOptions();
        $shopOptions = $shopResolver->distinctMappedShopRefsForChannel($channelForShops);
        if ($shopOptions->isEmpty()) {
            $shopOptions = $shopResolver->distinctShopRefsForChannel($channelForShops);
        }
        $shopRefToWarehouse = $shopResolver->shopRefToWarehouseMap();
        $showShopFilter = $shopOptions->count() > 1;
        $showShopColumn = $showShopFilter && blank($filters['shop_ref']);

        $nativePostSellStats = ($filters['view'] === 'orders' && $ordersTableExists)
            ? $postSellStats->counts()
            : null;

        $postSellByOrderId = [];
        $pendingWarehouseIds = [];
        $initialWarehouseLabels = [];
        if ($filters['view'] === 'orders' && $rows !== null) {
            foreach ($rows as $row) {
                $ariaName = $shopResolver->warehouseNameForListRow($row, $shopRefToWarehouse, $warehouseOptions);
                if ($ariaName !== null) {
                    $initialWarehouseLabels[$row->id] = [
                        'aria_name' => $ariaName,
                        'platform_wh' => $shopResolver->platformWarehouseLabelForListRow($row),
                        'unmapped' => false,
                    ];
                } elseif ($warehousePersist->orderNeedsWarehouseHydration($row)) {
                    $pendingWarehouseIds[] = (int) $row->id;
                }
            }

            $txnIds = $rows->pluck('posted_transaction_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $txnsById = $txnIds !== []
                ? Transaction::query()->whereIn('id', $txnIds)->get()->keyBy('id')
                : collect();

            foreach ($rows as $row) {
                $postedId = (int) ($row->posted_transaction_id ?? 0);
                $txn = $postedId > 0 ? $txnsById->get($postedId) : null;
                if ($txn === null) {
                    $invoice = $postSellEligibility->resolveInvoice($row);
                    if ($invoice !== '') {
                        $txn = Transaction::query()
                            ->where('type', Transaction::TYPE_SELL)
                            ->where('invoice', $invoice)
                            ->orderByDesc('id')
                            ->first();
                    }
                }

                $postSellByOrderId[$row->id] = [
                    'posted' => $postedId > 0 || $txn !== null,
                    'posted_transaction_id' => $txn?->id ?? ($postedId > 0 ? $postedId : null),
                    'submit_type_label' => $txn !== null
                        ? Transaction::submitTypeLabel((int) $txn->submit_type)
                        : null,
                ];
            }
        }

        return view('omnichannel.orders', [
            'filters' => $filters,
            'rows' => $rows,
            'eventsTableExists' => $eventsTableExists,
            'ordersTableExists' => $ordersTableExists,
            'statusOptions' => $statusOptions,
            'warehouseOptions' => $warehouseOptions,
            'shopOptions' => $shopOptions,
            'showShopColumn' => $showShopColumn,
            'showShopFilter' => $showShopFilter,
            'shopRefToWarehouse' => $shopRefToWarehouse,
            'warehouseNames' => $warehouseOptions,
            'shopResolver' => $shopResolver,
            'detailUrlTemplate' => url('/omnichannel/orders/__CHANNEL__/__ORDER__'),
            'statusPresenter' => $statusPresenter,
            'opsBucketStats' => $opsBucketStats,
            'opsBucketService' => $opsBucket,
            'nativePostSellStats' => $nativePostSellStats,
            'postSellByOrderId' => $postSellByOrderId,
            'jubelioActive' => (bool) config('services.jubelio.active'),
            'jubelioPendingSell' => Jubelioorder::query()->where('type', 'SELL')->where('status', 0)->count(),
            'canReprintAwb' => Omnichannel::userCanReprintAwb($request->user()),
            'warehouseLabelsUrl' => route('omnichannel.orders.warehouse-labels'),
            'pendingWarehouseIds' => $pendingWarehouseIds,
            'initialWarehouseLabels' => $initialWarehouseLabels,
        ]);
    }

    public function missedPushIndex(
        Request $request,
        MarketplaceMissedPushGapQuery $missedPushQuery,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $missedPushTableExists = Schema::hasTable('marketplace_missed_push_gaps');
        $filters = $missedPushQuery->parseFilters($request);
        $rows = null;

        if ($missedPushTableExists) {
            $rows = $missedPushQuery->openGapsQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();
        }

        $channelForShops = $filters['channel'] === 'all' ? null : $filters['channel'];
        $shopOptions = $shopResolver->distinctMappedShopRefsForChannel($channelForShops);
        if ($shopOptions->isEmpty()) {
            $shopOptions = $shopResolver->distinctShopRefsForChannel($channelForShops);
        }
        $showShopFilter = $shopOptions->count() > 1;

        return view('omnichannel.missed-push', [
            'filters' => $filters,
            'rows' => $rows,
            'missedPushTableExists' => $missedPushTableExists,
            'shopeeLastReconcileByShop' => $missedPushQuery->shopeeLastReconcileByShop(),
            'shopOptions' => $shopOptions,
            'showShopFilter' => $showShopFilter,
        ]);
    }

    public function ingestMissedPush(
        Request $request,
        int $gap,
        ShopeeMissedPushReconcileService $reconcile,
    ): RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        if (! Schema::hasTable('marketplace_missed_push_gaps')) {
            return back()->with('error', 'Tabel missed push belum ada — jalankan migrasi.');
        }

        $row = MarketplaceMissedPushGap::query()->find($gap);
        if ($row === null || $row->channel !== MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            return back()->with('error', 'Gap tidak ditemukan atau channel belum didukung untuk ingest manual.');
        }

        if (! $row->isOpen()) {
            return back()->with('warning', "Order {$row->order_ref} sudah di-ingest.");
        }

        $result = $reconcile->ingestGap($row);

        if (($result['error'] ?? null) !== null) {
            return back()->with('error', $result['error']);
        }

        if ($result['ingested'] ?? false) {
            $row->forceFill(['ingested_at' => now()])->save();

            return back()->with('success', "Order {$row->order_ref} di-ingest ke unified storage.");
        }

        return back()->with(
            'warning',
            'Pull selesai tetapi tidak ada layer ingest aktif (cek MARKETPLACE_WEBHOOK_UNIFIED_STORAGE / shadow / jubelio queue).',
        );
    }

    public function warehouseLabels(
        Request $request,
        MarketplaceChannelOrderWarehousePersist $warehousePersist,
        MarketplaceWebhookShopWarehouseResolver $shopResolver,
    ): JsonResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        if ($ids === []) {
            return response()->json(['labels' => []]);
        }

        $orders = MarketplaceChannelOrder::query()->whereIn('id', $ids)->get();
        $warehousePersist->hydrateListPage($orders);

        $shopRefToWarehouse = $shopResolver->shopRefToWarehouseMap();
        $warehouseNames = $shopResolver->warehouseOptions();

        $labels = [];
        foreach ($orders as $order) {
            $ariaName = $shopResolver->warehouseNameForListRow($order, $shopRefToWarehouse, $warehouseNames);
            $unmapped = filled($order->shop_ref) && $ariaName === null;
            $labels[(string) $order->id] = [
                'aria_name' => $ariaName,
                'platform_wh' => $shopResolver->platformWarehouseLabelForListRow($order),
                'unmapped' => $unmapped,
            ];
        }

        return response()->json(['labels' => $labels]);
    }

    public function show(
        Request $request,
        string $channel,
        string $orderRef,
        OmnichannelOrderDetailReader $detailReader,
        MarketplaceWebhookStatusPresenter $statusPresenter,
    ): JsonResponse|View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $channel = strtolower($channel);
        if (! in_array($channel, ['shopee', 'tiktok'], true)) {
            abort(404);
        }

        if (Schema::hasTable('marketplace_channel_orders')) {
            $exists = MarketplaceChannelOrder::query()
                ->where('channel', $channel)
                ->where('order_ref', $orderRef)
                ->exists();
            if (! $exists) {
                abort(404);
            }
        }

        $payload = $detailReader->build($channel, $orderRef);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($payload);
        }

        return view('omnichannel.partials.order-detail-panel', [
            'detail' => $payload,
            'statusPresenter' => $statusPresenter,
        ]);
    }

    public function showTikTokPage(
        Request $request,
        string $orderRef,
        OmnichannelOrderDetailReader $detailReader,
        MarketplaceWebhookStatusPresenter $statusPresenter,
    ): View {
        return $this->showChannelPage($request, 'tiktok', $orderRef, $detailReader, $statusPresenter);
    }

    public function showTokopediaPage(
        Request $request,
        string $orderRef,
        OmnichannelOrderDetailReader $detailReader,
        MarketplaceWebhookStatusPresenter $statusPresenter,
    ): View {
        return $this->showChannelPage($request, 'tokopedia', $orderRef, $detailReader, $statusPresenter);
    }

    public function showChannelPage(
        Request $request,
        string $channel,
        string $orderRef,
        OmnichannelOrderDetailReader $detailReader,
        MarketplaceWebhookStatusPresenter $statusPresenter,
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $storageChannel = $this->resolveStorageChannel(strtolower($channel));

        if (Schema::hasTable('marketplace_channel_orders')) {
            $exists = MarketplaceChannelOrder::query()
                ->where('channel', $storageChannel)
                ->where('order_ref', $orderRef)
                ->exists();
            if (! $exists) {
                abort(404);
            }
        }

        $detail = $detailReader->build($storageChannel, $orderRef);

        return view('omnichannel.channel-order-show', [
            'channelLabel' => $channel === 'tokopedia' ? 'Tokopedia' : ucfirst($storageChannel),
            'storageChannel' => $storageChannel,
            'detail' => $detail,
            'statusPresenter' => $statusPresenter,
        ]);
    }

    private function resolveStorageChannel(string $channel): string
    {
        return match ($channel) {
            'tokopedia' => 'tiktok',
            default => $channel,
        };
    }

    public function requestShippingDocuments(
        Request $request,
        ShopeeShippingDocumentService $shopeeAwb,
        TikTokShopShippingDocumentService $tiktokAwb,
        MarketplaceChannelOrderAwbPrintGuard $awbPrintGuard,
        MarketplaceChannelOrderAwbDocumentState $awbDocumentState,
    ): RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $parsed = $this->parseBulkAwbSelection($request, $shopeeAwb, $tiktokAwb);
        if ($parsed instanceof RedirectResponse) {
            return $parsed;
        }

        $shopee = $parsed['shopee'];
        $tiktok = $parsed['tiktok'];
        $skippedUnsupported = (int) ($parsed['skipped_unsupported'] ?? 0);

        $shopeeChannel = MarketplaceWebhookRecorder::CHANNEL_SHOPEE;
        $tiktokChannel = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;

        $shopeePartition = $awbPrintGuard->partitionOrdersForAwbPrint($shopeeChannel, $shopee['order_refs'], $request->user());
        $shopeeFiltered = $this->filterByShopOrderRefs($shopee['by_shop'], $shopeePartition['allowed']);

        $tiktokPartition = $awbPrintGuard->partitionOrdersForAwbPrint($tiktokChannel, $tiktok['order_refs'], $request->user());
        $tiktokFiltered = $this->filterByCipherOrderRefs($tiktok['by_cipher'], $tiktokPartition['allowed']);

        if ($shopeeFiltered['order_refs'] === [] && $tiktokFiltered['order_refs'] === []) {
            return redirect()->back()->with(
                'error',
                'Tidak ada order yang bisa diminta AWB-nya (mungkin sudah dicetak atau channel tidak didukung).',
            );
        }

        if ($shopeeFiltered['order_refs'] !== []) {
            $awbDocumentState->markDocumentRequested($shopeeChannel, $shopeeFiltered['order_refs']);
        }
        if ($tiktokFiltered['order_refs'] !== []) {
            $awbDocumentState->markDocumentRequested($tiktokChannel, $tiktokFiltered['order_refs']);
        }

        try {
            foreach ($shopeeFiltered['by_shop'] as $shopId => $orderSns) {
                $shopeeAwb->prepareShippingDocumentsForShop((int) $shopId, array_values(array_unique($orderSns)));
            }
            foreach ($tiktokFiltered['by_cipher'] as $shopCipher => $orderIds) {
                $tiktokAwb->prepareShippingDocumentsForShop((string) $shopCipher, array_values(array_unique($orderIds)));
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($shopeeFiltered['order_refs'] !== []) {
            $awbDocumentState->markDocumentReady($shopeeChannel, $shopeeFiltered['order_refs']);
        }
        if ($tiktokFiltered['order_refs'] !== []) {
            $awbDocumentState->markDocumentReady($tiktokChannel, $tiktokFiltered['order_refs']);
        }

        $parts = [];
        if ($shopeeFiltered['order_refs'] !== []) {
            $parts[] = count($shopeeFiltered['order_refs']).' order Shopee';
        }
        if ($tiktokFiltered['order_refs'] !== []) {
            $parts[] = count($tiktokFiltered['order_refs']).' order TikTok/Tokopedia';
        }

        if ($shopeePartition['blocked'] !== []) {
            session()->flash('warning', count($shopeePartition['blocked']).' order Shopee dilewati (sudah dicetak).');
        }
        if ($tiktokPartition['blocked'] !== []) {
            session()->flash('warning', count($tiktokPartition['blocked']).' order TikTok/Tokopedia dilewati (sudah dicetak).');
        }
        if ($skippedUnsupported > 0) {
            session()->flash('warning', "{$skippedUnsupported} baris channel lain dilewati.");
        }

        return redirect()->back()->with('success', implode(', ', $parts).': AWB siap diunduh.');
    }

    public function downloadShippingDocuments(
        Request $request,
        ShopeeShippingDocumentService $shopeeAwb,
        TikTokShopShippingDocumentService $tiktokAwb,
        MarketplaceChannelOrderAwbPromoter $awbStatusPromoter,
        MarketplaceChannelOrderAwbPrintGuard $awbPrintGuard,
        MarketplaceChannelOrderAwbDocumentState $awbDocumentState,
    ): StreamedResponse|RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $parsed = $this->parseBulkAwbSelection($request, $shopeeAwb, $tiktokAwb);
        if ($parsed instanceof RedirectResponse) {
            return $parsed;
        }

        $shopee = $parsed['shopee'];
        $tiktok = $parsed['tiktok'];
        $skippedUnsupported = (int) ($parsed['skipped_unsupported'] ?? 0);

        $shopeeChannel = MarketplaceWebhookRecorder::CHANNEL_SHOPEE;
        $tiktokChannel = MarketplaceWebhookRecorder::CHANNEL_TIKTOK;

        $allowedShopee = $this->filterAwbDownloadChannel(
            $shopeeChannel,
            $shopee,
            $awbPrintGuard,
            $awbDocumentState,
            $request,
        );
        if ($allowedShopee instanceof RedirectResponse) {
            return $allowedShopee;
        }

        $allowedTiktok = $this->filterAwbDownloadChannel(
            $tiktokChannel,
            $tiktok,
            $awbPrintGuard,
            $awbDocumentState,
            $request,
        );
        if ($allowedTiktok instanceof RedirectResponse) {
            return $allowedTiktok;
        }

        if ($allowedShopee['order_refs'] === [] && $allowedTiktok['order_refs'] === []) {
            $shopeePrintPartition = $awbPrintGuard->partitionOrdersForAwbPrint(
                $shopeeChannel,
                $shopee['order_refs'],
                $request->user(),
            );
            if ($shopee['order_refs'] !== [] && $shopeePrintPartition['allowed'] === [] && $shopeePrintPartition['blocked'] !== []) {
                return redirect()->back()->with(
                    'error',
                    'AWB sudah pernah dicetak untuk order Shopee: '.implode(', ', $shopeePrintPartition['blocked'])
                    .'. Minta izin cetak ulang ke admin.',
                );
            }

            $tiktokPrintPartition = $awbPrintGuard->partitionOrdersForAwbPrint(
                $tiktokChannel,
                $tiktok['order_refs'],
                $request->user(),
            );
            if ($tiktok['order_refs'] !== [] && $tiktokPrintPartition['allowed'] === [] && $tiktokPrintPartition['blocked'] !== []) {
                return redirect()->back()->with(
                    'error',
                    'AWB sudah pernah dicetak untuk order TikTok/Tokopedia: '.implode(', ', $tiktokPrintPartition['blocked'])
                    .'. Minta izin cetak ulang ke admin.',
                );
            }

            return redirect()->back()->with('error', 'Tidak ada order yang valid untuk diunduh AWB-nya.');
        }

        $files = [];
        try {
            foreach ($allowedShopee['by_shop'] as $shopId => $orderSns) {
                $orderSns = array_values(array_unique($orderSns));
                $pdfs = $shopeeAwb->downloadPdfBatchesForShop((int) $shopId, $orderSns, assumeDocumentsPrepared: true);
                $batchIndex = 0;
                foreach ($pdfs as $pdfBinary) {
                    $suffix = count($pdfs) > 1 ? '-batch-'.(++$batchIndex) : '';
                    $files['shopee-shop-'.$shopId.$suffix.'.pdf'] = $pdfBinary;
                }
            }

            foreach ($allowedTiktok['by_cipher'] as $shopCipher => $orderIds) {
                $orderIds = array_values(array_unique($orderIds));
                $pdfs = $tiktokAwb->downloadPdfBatchesForShop((string) $shopCipher, $orderIds, assumeDocumentsPrepared: true);
                $safeCipher = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $shopCipher) ?: 'shop';
                $batchIndex = 0;
                foreach ($pdfs as $pdfBinary) {
                    $orderId = $orderIds[$batchIndex] ?? ($batchIndex + 1);
                    $suffix = count($pdfs) > 1 ? '-'.$orderId : '';
                    $files['tiktok-'.$safeCipher.$suffix.'.pdf'] = $pdfBinary;
                    $batchIndex++;
                }
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $printerUserId = (int) $request->user()->id;
        if ($allowedShopee['order_refs'] !== []) {
            $awbStatusPromoter->promoteShopeeOrdersAfterAwb($allowedShopee['order_refs']);
            $awbPrintGuard->recordSuccessfulPrints($shopeeChannel, $allowedShopee['order_refs'], $request->user());
        }
        if ($allowedTiktok['order_refs'] !== []) {
            $awbStatusPromoter->promoteTikTokOrdersAfterAwb($allowedTiktok['order_refs']);
            $awbPrintGuard->recordSuccessfulPrints($tiktokChannel, $allowedTiktok['order_refs'], $request->user());
        }

        $awbStatusPromoter->ensureJubelioQueueAfterAwb(
            $allowedShopee['by_shop'],
            $allowedTiktok['by_cipher'],
            $printerUserId,
        );

        if ($skippedUnsupported > 0) {
            session()->flash('warning', "{$skippedUnsupported} baris channel lain dilewati.");
        }

        return $this->streamAwbFiles($files);
    }

    /**
     * @param  array{by_shop?: array<int, list<string>>, by_cipher?: array<string, list<string>>, order_refs: list<string>}  $channelSelection
     * @return array{by_shop: array<int, list<string>>, by_cipher: array<string, list<string>>, order_refs: list<string>}|RedirectResponse
     */
    private function filterAwbDownloadChannel(
        string $channel,
        array $channelSelection,
        MarketplaceChannelOrderAwbPrintGuard $awbPrintGuard,
        MarketplaceChannelOrderAwbDocumentState $awbDocumentState,
        Request $request,
    ): array|RedirectResponse {
        $orderRefs = $channelSelection['order_refs'] ?? [];
        if ($orderRefs === []) {
            return [
                'by_shop' => [],
                'by_cipher' => [],
                'order_refs' => [],
            ];
        }

        $partition = $awbPrintGuard->partitionOrdersForAwbPrint($channel, $orderRefs, $request->user());

        if ($partition['blocked'] !== []) {
            $label = $channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE ? 'Shopee' : 'TikTok/Tokopedia';
            session()->flash(
                'warning',
                count($partition['blocked'])." order {$label} dilewati karena AWB sudah pernah dicetak: ".implode(', ', $partition['blocked']),
            );
        }

        $readyPartition = $awbDocumentState->partitionOrdersForAwbDownload($channel, $partition['allowed']);

        if ($readyPartition['blocked'] !== []) {
            session()->flash(
                'warning',
                count($readyPartition['blocked']).' order dilewati karena belum siap AWB: '
                .implode(', ', $readyPartition['blocked']),
            );
        }

        $byShop = [];
        $byCipher = [];
        if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            $filtered = $this->filterByShopOrderRefs($channelSelection['by_shop'] ?? [], $readyPartition['allowed']);

            return [
                'by_shop' => $filtered['by_shop'],
                'by_cipher' => [],
                'order_refs' => $filtered['order_refs'],
            ];
        }

        $filtered = $this->filterByCipherOrderRefs($channelSelection['by_cipher'] ?? [], $readyPartition['allowed']);

        return [
            'by_shop' => [],
            'by_cipher' => $filtered['by_cipher'],
            'order_refs' => $filtered['order_refs'],
        ];
    }

    /**
     * @return array{
     *     shopee: array{by_shop: array<int, list<string>>, order_refs: list<string>},
     *     tiktok: array{by_cipher: array<string, list<string>>, order_refs: list<string>},
     *     skipped_unsupported: int,
     * }|RedirectResponse
     */
    private function parseBulkAwbSelection(
        Request $request,
        ShopeeShippingDocumentService $shopeeAwb,
        TikTokShopShippingDocumentService $tiktokAwb,
    ): array|RedirectResponse {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.channel' => ['required', 'string', 'max:32'],
            'rows.*.order_ref' => ['required', 'string', 'max:64'],
            'rows.*.shop_ref' => ['nullable', 'string', 'max:64'],
        ]);

        $skippedUnsupported = 0;
        $byShop = [];
        $byCipher = [];
        $shopeeOrderRefs = [];
        $tiktokOrderRefs = [];

        foreach ($validated['rows'] as $row) {
            $channel = strtolower(trim((string) $row['channel']));
            $orderRef = trim((string) $row['order_ref']);
            if ($orderRef === '') {
                continue;
            }

            if ($channel === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
                $shopId = $shopeeAwb->resolveShopId($row['shop_ref'] ?? null);
                if ($shopId === null) {
                    return redirect()
                        ->back()
                        ->with('error', "Shop Shopee tidak dikenali atau belum OAuth: shop_ref «{$row['shop_ref']}» (order {$orderRef}).");
                }

                $byShop[$shopId][] = $orderRef;
                $shopeeOrderRefs[] = $orderRef;

                continue;
            }

            if ($channel === MarketplaceWebhookRecorder::CHANNEL_TIKTOK) {
                $shopCipher = $tiktokAwb->resolveShopCipher($row['shop_ref'] ?? null);
                if ($shopCipher === null) {
                    return redirect()
                        ->back()
                        ->with('error', "Shop TikTok tidak dikenali: shop_ref «{$row['shop_ref']}» (order {$orderRef}). Mapping shop_cipher di TikTok sync / env.");
                }

                $byCipher[$shopCipher][] = $orderRef;
                $tiktokOrderRefs[] = $orderRef;

                continue;
            }

            $skippedUnsupported++;
        }

        if ($byShop === [] && $byCipher === []) {
            $message = $skippedUnsupported > 0
                ? 'Cetak AWB mendukung Shopee dan TikTok/Tokopedia — baris lain dilewati.'
                : 'Tidak ada baris Shopee atau TikTok yang valid dipilih.';

            return redirect()->back()->with('error', $message);
        }

        return [
            'shopee' => [
                'by_shop' => $byShop,
                'order_refs' => array_values(array_unique($shopeeOrderRefs)),
            ],
            'tiktok' => [
                'by_cipher' => $byCipher,
                'order_refs' => array_values(array_unique($tiktokOrderRefs)),
            ],
            'skipped_unsupported' => $skippedUnsupported,
        ];
    }

    /**
     * @param  array<string, list<string>>  $byCipher
     * @param  list<string>  $allowedOrderRefs
     * @return array{by_cipher: array<string, list<string>>, order_refs: list<string>}
     */
    private function filterByCipherOrderRefs(array $byCipher, array $allowedOrderRefs): array
    {
        $allowedSet = array_fill_keys($allowedOrderRefs, true);
        foreach ($byCipher as $cipher => $orderIds) {
            $byCipher[$cipher] = array_values(array_filter(
                $orderIds,
                static fn ($id) => isset($allowedSet[$id]),
            ));
            if ($byCipher[$cipher] === []) {
                unset($byCipher[$cipher]);
            }
        }

        $remainingRefs = [];
        foreach ($byCipher as $orderIds) {
            foreach ($orderIds as $id) {
                $remainingRefs[] = $id;
            }
        }

        return [
            'by_cipher' => $byCipher,
            'order_refs' => array_values(array_unique($remainingRefs)),
        ];
    }

    /**
     * @param  array<int, list<string>>  $byShop
     * @param  list<string>  $allowedOrderRefs
     * @return array{by_shop: array<int, list<string>>, order_refs: list<string>}
     */
    private function filterByShopOrderRefs(array $byShop, array $allowedOrderRefs): array
    {
        $allowedSet = array_fill_keys($allowedOrderRefs, true);
        foreach ($byShop as $shopId => $orderSns) {
            $byShop[$shopId] = array_values(array_filter(
                $orderSns,
                static fn ($sn) => isset($allowedSet[$sn]),
            ));
            if ($byShop[$shopId] === []) {
                unset($byShop[$shopId]);
            }
        }

        $remainingRefs = [];
        foreach ($byShop as $orderSns) {
            foreach ($orderSns as $sn) {
                $remainingRefs[] = $sn;
            }
        }

        return [
            'by_shop' => $byShop,
            'order_refs' => array_values(array_unique($remainingRefs)),
        ];
    }

    /**
     * @param  array<string, string>  $files
     */
    private function streamAwbFiles(array $files): StreamedResponse
    {
        if (count($files) === 1) {
            $filename = array_key_first($files);
            $binary = $files[$filename];

            return response()->streamDownload(
                static function () use ($binary) {
                    echo $binary;
                },
                $filename,
                ['Content-Type' => 'application/pdf'],
            );
        }

        return response()->streamDownload(
            function () use ($files) {
                $tmp = tempnam(sys_get_temp_dir(), 'awb-zip-');
                if ($tmp === false) {
                    throw new \RuntimeException('Gagal membuat file sementara.');
                }

                $zip = new ZipArchive;
                if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                    @unlink($tmp);

                    throw new \RuntimeException('Gagal membuka arsip ZIP.');
                }

                foreach ($files as $name => $contents) {
                    $zip->addFromString($name, $contents);
                }
                $zip->close();

                readfile($tmp);
                @unlink($tmp);
            },
            'omnichannel-awb-'.now()->format('Y-m-d-His').'.zip',
            ['Content-Type' => 'application/zip'],
        );
    }
}