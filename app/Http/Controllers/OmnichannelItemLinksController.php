<?php

namespace App\Http\Controllers;

use App\Models\Omnichannel;
use App\Models\ShopeeStock;
use App\Models\TikTokShop;
use App\Services\Marketplace\MarketplaceUnlinkedItemsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OmnichannelItemLinksController extends Controller
{
    public function index(Request $request, MarketplaceUnlinkedItemsService $service): View
    {
        $this->authorizePage();

        $filters = [
            'warehouse_id' => $request->query('warehouse_id'),
            'channel' => $request->query('channel', MarketplaceUnlinkedItemsService::CHANNEL_BOTH),
            'stock_only' => $request->boolean('stock_only', true),
            'q' => $request->query('q', ''),
        ];

        if ($filters['warehouse_id'] === null && $service->defaultWarehouseId() !== null) {
            $filters['warehouse_id'] = $service->defaultWarehouseId();
        }

        $warehouseOptions = $service->warehouseOptions();
        $items = $service->paginate($filters, 50);

        return view('omnichannel.item-links', [
            'items' => $items,
            'warehouseOptions' => $warehouseOptions,
            'filters' => $service->normalizeFilters($filters),
            'can' => [
                'shopee' => Gate::check(ShopeeStock::getPermissions()['sync']),
                'tiktok' => Gate::any([
                    TikTokShop::getPermissions()['item_link'],
                    TikTokShop::getPermissions()['view'],
                ]),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function export(Request $request, MarketplaceUnlinkedItemsService $service): StreamedResponse
    {
        $this->authorizePage();

        $filters = [
            'warehouse_id' => $request->query('warehouse_id'),
            'channel' => $request->query('channel', MarketplaceUnlinkedItemsService::CHANNEL_BOTH),
            'stock_only' => $request->boolean('stock_only', true),
            'q' => $request->query('q', ''),
        ];

        $filename = 'omnichannel-unlinked-items-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($service, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'code',
                'name',
                'warehouse_qty',
                'missing_fields',
                'shopee_unlinked',
                'tiktok_unlinked',
                'item_id',
            ]);

            foreach ($service->exportRows($filters) as $row) {
                fputcsv($out, [
                    $row['code'],
                    $row['name'],
                    $row['warehouse_qty'],
                    implode('|', $row['missing_fields']),
                    $row['shopee_unlinked'] ? '1' : '0',
                    $row['tiktok_unlinked'] ? '1' : '0',
                    $row['id'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function authorizePage(): void
    {
        abort_unless(Gate::any([
            Omnichannel::getPermissions()['view'],
            ShopeeStock::getPermissions()['sync'],
            TikTokShop::getPermissions()['item_link'],
        ]), 403);
    }
}