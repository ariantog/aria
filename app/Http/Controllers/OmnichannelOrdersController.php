<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceChannelOrder;
use App\Models\Omnichannel;
use App\Services\Marketplace\MarketplaceChannelOrderAwbPrintGuard;
use App\Services\Marketplace\MarketplaceChannelOrderAwbPromoter;
use App\Services\Marketplace\MarketplaceChannelOrderWarehousePersist;
use App\Services\Marketplace\MarketplaceChannelOrderOpsBucket;
use App\Services\Marketplace\MarketplaceWebhookInboxQuery;
use App\Services\Marketplace\MarketplaceWebhookStatusPresenter;
use App\Services\Marketplace\MarketplaceWebhookShopWarehouseResolver;
use App\Services\Marketplace\OmnichannelOrderDetailReader;
use App\Services\Shopee\ShopeeShippingDocumentService;
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
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

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
                } elseif (filled($row->shop_ref)) {
                    $initialWarehouseLabels[$row->id] = [
                        'aria_name' => null,
                        'platform_wh' => $shopResolver->platformWarehouseLabelForListRow($row),
                        'unmapped' => true,
                    ];
                }
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
            'canReprintAwb' => Omnichannel::userCanReprintAwb($request->user()),
            'warehouseLabelsUrl' => route('omnichannel.orders.warehouse-labels'),
            'pendingWarehouseIds' => $pendingWarehouseIds,
            'initialWarehouseLabels' => $initialWarehouseLabels,
        ]);
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

    public function downloadShippingDocuments(
        Request $request,
        ShopeeShippingDocumentService $awbService,
        MarketplaceChannelOrderAwbPromoter $awbStatusPromoter,
        MarketplaceChannelOrderAwbPrintGuard $awbPrintGuard,
    ): StreamedResponse|RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.channel' => ['required', 'string', 'max:32'],
            'rows.*.order_ref' => ['required', 'string', 'max:64'],
            'rows.*.shop_ref' => ['nullable', 'string', 'max:64'],
        ]);

        $skippedNonShopee = 0;
        $byShop = [];
        $shopeeOrderRefs = [];
        foreach ($validated['rows'] as $row) {
            $channel = strtolower(trim((string) $row['channel']));
            if ($channel !== 'shopee') {
                $skippedNonShopee++;

                continue;
            }

            $orderRef = trim((string) $row['order_ref']);
            if ($orderRef === '') {
                continue;
            }

            $shopId = $awbService->resolveShopId($row['shop_ref'] ?? null);
            if ($shopId === null) {
                return redirect()
                    ->back()
                    ->with('error', "Shop tidak dikenali atau belum OAuth: shop_ref «{$row['shop_ref']}» (order {$orderRef}).");
            }

            $byShop[$shopId][] = $orderRef;
            $shopeeOrderRefs[] = $orderRef;
        }

        if ($byShop === []) {
            $message = $skippedNonShopee > 0
                ? 'Cetak AWB v1 hanya mendukung Shopee — TikTok/Tokopedia dilewati.'
                : 'Tidak ada baris Shopee yang valid dipilih.';

            return redirect()->back()->with('error', $message);
        }

        $shopeeOrderRefs = array_values(array_unique($shopeeOrderRefs));
        $partition = $awbPrintGuard->partitionOrdersForAwbPrint($shopeeOrderRefs, $request->user());
        $blockedAlreadyPrinted = $partition['blocked'];
        $allowedOrderRefs = $partition['allowed'];

        if ($allowedOrderRefs === []) {
            $sns = implode(', ', $blockedAlreadyPrinted);

            return redirect()->back()->with(
                'error',
                'AWB sudah pernah dicetak untuk order Shopee: '.$sns.'. Minta izin cetak ulang ke admin.',
            );
        }

        if ($blockedAlreadyPrinted !== []) {
            $skippedList = implode(', ', $blockedAlreadyPrinted);
            session()->flash(
                'warning',
                count($blockedAlreadyPrinted).' order dilewati karena AWB sudah pernah dicetak: '.$skippedList,
            );
        }

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
        $shopeeOrderRefs = $allowedOrderRefs;

        $files = [];
        try {
            foreach ($byShop as $shopId => $orderSns) {
                $orderSns = array_values(array_unique($orderSns));
                $pdfs = $awbService->downloadPdfBatchesForShop((int) $shopId, $orderSns);
                $batchIndex = 0;
                foreach ($pdfs as $pdfBinary) {
                    $suffix = count($pdfs) > 1 ? '-batch-'.(++$batchIndex) : '';
                    $files['shopee-shop-'.$shopId.$suffix.'.pdf'] = $pdfBinary;
                }
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $printerUserId = (int) $request->user()->id;
        $awbStatusPromoter->promoteShopeeOrdersAfterAwb($shopeeOrderRefs);
        $awbPrintGuard->recordSuccessfulPrints($shopeeOrderRefs, $request->user());
        $awbStatusPromoter->ensureJubelioQueueAfterAwb($byShop, $printerUserId);

        if ($skippedNonShopee > 0) {
            session()->flash(
                'warning',
                "{$skippedNonShopee} baris non-Shopee (TikTok/Tokopedia) tidak dicetak — v1 Shopee saja.",
            );
        }

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
            'shopee-awb-'.now()->format('Y-m-d-His').'.zip',
            ['Content-Type' => 'application/zip'],
        );
    }
}