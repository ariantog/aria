<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceChannelOrder;
use App\Models\Omnichannel;
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
            $warehousePersist->hydrateListPage($rows->getCollection());
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
        $shopOptions = $shopResolver->distinctShopRefsForChannel($channelForShops);
        $shopRefToWarehouse = $shopResolver->shopRefToWarehouseMap();

        return view('omnichannel.orders', [
            'filters' => $filters,
            'rows' => $rows,
            'eventsTableExists' => $eventsTableExists,
            'ordersTableExists' => $ordersTableExists,
            'statusOptions' => $statusOptions,
            'warehouseOptions' => $warehouseOptions,
            'shopOptions' => $shopOptions,
            'shopRefToWarehouse' => $shopRefToWarehouse,
            'warehouseNames' => $warehouseOptions,
            'shopResolver' => $shopResolver,
            'detailUrlTemplate' => url('/omnichannel/orders/__CHANNEL__/__ORDER__'),
            'statusPresenter' => $statusPresenter,
            'opsBucketStats' => $opsBucketStats,
            'opsBucketService' => $opsBucket,
        ]);
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
        $awbStatusPromoter->recordShopeeAwbPrint($shopeeOrderRefs, $printerUserId);
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