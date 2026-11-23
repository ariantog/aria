<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\ShopeeFlexiShop;
use App\Models\ShopeeStock;
use Illuminate\Support\Facades\Schema;
use App\Services\Marketplace\ShopeeFlexiOrderLookupService;
use App\Services\Marketplace\ShopeeOrderPollService;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeFlexiOrderLookupController extends Controller
{
    public function show(Request $request, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $status = $shopeeApi->getConnectionStatus();

        return $this->lookupView($shopeeApi, [
            'orderRef' => (string) $request->old('order_ref', ''),
            'shopId' => (string) $request->old('shop_id', ''),
            'result' => null,
            'ingestResult' => null,
        ]);
    }

    public function store(Request $request, ShopeeFlexiOrderLookupService $lookup, ShopeeAdminOpenApiService $shopeeApi): View
    {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $status = $shopeeApi->getConnectionStatus();

        return $this->lookupView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'result' => $lookup->lookup($shopId, $orderRef),
            'ingestResult' => null,
        ]);
    }

    public function ingest(
        Request $request,
        ShopeeFlexiOrderLookupService $lookup,
        ShopeeOrderPollService $poll,
        ShopeeSyncInvoicePrefix $invoicePrefix,
        ShopeeAdminOpenApiService $shopeeApi,
    ): View {
        Gate::authorize(ShopeeStock::getPermissions()['sync']);

        $validated = $request->validate([
            'order_ref' => 'required|string|max:128',
            'shop_id' => 'required|integer|min:1',
        ]);

        $shopId = (int) $validated['shop_id'];
        $orderRef = (string) $validated['order_ref'];
        $orderSn = $lookup->normalizeOrderSn($orderRef);

        $ingest = $poll->pullAndIngestOrder($shopId, $orderSn);
        $ingest['invoice_preview'] = $invoicePrefix->invoiceForOrderSn($shopId, $orderSn);
        $ingest['omnichannel_url'] = route('omnichannel.shopee.show', ['ordersn' => $orderSn]);

        if ($ingest['error'] === null) {
            $queue = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $ingest['invoice_preview'])
                ->first();
            if ($queue !== null) {
                $ingest['jubelio_queue_url'] = route('jubelio.show', $queue);
            }
        }

        $lookupResult = $ingest['error'] === null
            ? $lookup->lookup($shopId, $orderRef)
            : null;

        return $this->lookupView($shopeeApi, [
            'orderRef' => $orderRef,
            'shopId' => (string) $shopId,
            'result' => $lookupResult,
            'ingestResult' => $ingest,
        ]);
    }

    /**
     * @param  array{orderRef: string, shopId: string, result: array<string, mixed>|null, ingestResult: array<string, mixed>|null}  $data
     */
    private function lookupView(ShopeeAdminOpenApiService $shopeeApi, array $data): View
    {
        $status = $shopeeApi->getConnectionStatus();

        return view('shopee.flexi.lookup', array_merge($data, [
            'authorizedShopIds' => $status['authorized_shop_ids'] ?? [],
            'shopOptions' => $this->shopOptionsForLookup($status['authorized_shop_ids'] ?? [], (string) ($data['shopId'] ?? '')),
        ]));
    }

    /**
     * @param  list<int|string>  $authorizedShopIds
     * @return list<array{id: int, label: string}>
     */
    private function shopOptionsForLookup(array $authorizedShopIds, string $selectedShopId): array
    {
        $options = [];
        $prefixLabels = config('shopee_flexi.flexi_prefixes', []);

        if (Schema::hasTable('shopee_flexi_shops')) {
            $rows = ShopeeFlexiShop::query()->orderBy('shop_id')->get();
            if ($rows->isNotEmpty()) {
                foreach ($rows as $row) {
                    $prefix = strtoupper((string) $row->invoice_prefix);
                    $parts = [(string) (int) $row->shop_id, $prefix];
                    if (filled($row->label)) {
                        $parts[] = (string) $row->label;
                    } elseif (filled($prefixLabels[$prefix] ?? null)) {
                        $parts[] = (string) $prefixLabels[$prefix];
                    }
                    $options[] = [
                        'id' => (int) $row->shop_id,
                        'label' => implode(' — ', $parts),
                    ];
                }

                return $this->ensureSelectedShopOption($options, $selectedShopId);
            }
        }

        foreach ($authorizedShopIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $options[] = [
                'id' => $id,
                'label' => (string) $id.' (OAuth — belum di /shopee-flexi)',
            ];
        }

        return $this->ensureSelectedShopOption($options, $selectedShopId);
    }

    /**
     * @param  list<array{id: int, label: string}>  $options
     * @return list<array{id: int, label: string}>
     */
    private function ensureSelectedShopOption(array $options, string $selectedShopId): array
    {
        $selected = (int) $selectedShopId;
        if ($selected <= 0) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int) $option['id'] === $selected) {
                return $options;
            }
        }

        $options[] = [
            'id' => $selected,
            'label' => $selected.' (posting — tidak ada di daftar FLEXI saat ini)',
        ];

        return $options;
    }
}