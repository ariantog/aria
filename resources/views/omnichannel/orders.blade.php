@extends('layouts.app')

@php
$isEventsView = ($filters['view'] ?? 'events') === 'events';
$pageTitle = $isEventsView ? 'Omnichannel — Webhooks' : 'Omnichannel — Orders';
$crumbLabel = $isEventsView ? 'Webhooks' : 'Orders';
@endphp

@section('title', $pageTitle)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.orders.index', ['view' => $filters['view'] ?? 'events'])],
    ['title' => $crumbLabel, 'href' => route('omnichannel.orders.index', ['view' => $filters['view'] ?? 'events'])],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
$channelLabels = ['shopee' => 'Shopee', 'tiktok' => 'TikTok', 'tokopedia' => 'Tokopedia'];
$omnichannelChannelLabel = static function (object $row) use ($channelLabels): string {
    if (strtoupper((string) ($row->commerce_platform ?? '')) === 'TOKOPEDIA') {
        return 'Tokopedia';
    }

    return match ($row->channel ?? '') {
        'shopee' => 'Shopee',
        'tiktok' => 'TikTok',
        default => $channelLabels[$row->channel ?? ''] ?? (string) ($row->channel ?? '—'),
    };
};
$resetQuery = ['view' => $filters['view'] ?? 'events'];
$currentOrdersUrl = route('omnichannel.orders.index', array_merge(request()->query(), ['view' => 'orders']));
$pageOrderRows = [];
if (! $isEventsView && isset($rows) && $rows !== null) {
    foreach ($rows as $orderRow) {
        $pageOrderRows[] = [
            'channel' => (string) $orderRow->channel,
            'order_ref' => (string) $orderRow->order_ref,
            'shop_ref' => (string) ($orderRow->shop_ref ?? ''),
            'awb_printed' => filled($orderRow->awb_printed_at ?? null),
            'awb_ready' => filled($orderRow->awb_document_ready_at ?? null),
            'awb_pending' => filled($orderRow->awb_document_requested_at ?? null)
                && blank($orderRow->awb_document_ready_at ?? null),
        ];
    }
}
$hasActiveFilters = collect(request()->query())
    ->only(['channel', 'view', 'date_from', 'date_to', 'order_ref', 'order_status', 'shop_ref', 'warehouse_id', 'per_page', 'ops_bucket'])
    ->filter(fn ($value) => $value !== null && $value !== '')
    ->isNotEmpty();
$activeOpsBucket = $filters['ops_bucket'] ?? null;
$opsBucketStats = $opsBucketStats ?? [];
$opsBucketService = $opsBucketService ?? null;
$showShopColumn = $showShopColumn ?? ($shopOptions->count() > 1 && blank($filters['shop_ref'] ?? ''));
$ordersTableColspan = $showShopColumn ? 12 : 11;
$eventsTableColspan = $showShopColumn ? 11 : 10;
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4"
     data-testid="omnichannel-orders-page"
     x-data="{
         expandedKey: null,
         expandedId: null,
         detailHtml: {},
         loadingKey: null,
         warehouseById: @js($initialWarehouseLabels ?? []),
         warehouseLoadingIds: @js($pendingWarehouseIds ?? []),
         warehouseLabelsUrl: @js($warehouseLabelsUrl ?? ''),
         warehouseLabelsFetchInFlight: false,
         pageRows: @js($pageOrderRows),
         canReprintAwb: @js($canReprintAwb ?? false),
         awbPrinting: false,
         awbRequesting: false,
         selected: {},
         rowAwbSelectable(row) {
             return this.canReprintAwb || !row.awb_printed;
         },
         rowKey(channel, orderRef) {
             return channel + ':' + orderRef;
         },
         selectionKey(row) {
             return row.channel + ':' + row.order_ref;
         },
         isRowSelected(row) {
             return !!this.selected[this.selectionKey(row)];
         },
         toggleRowSelection(row) {
             const k = this.selectionKey(row);
             if (this.selected[k]) {
                 delete this.selected[k];
             } else {
                 this.selected[k] = row;
             }
         },
         selectedCount() {
             return Object.keys(this.selected).length;
         },
         selectablePageRows() {
             return this.pageRows.filter((row) => this.rowAwbSelectable(row));
         },
         allPageSelected() {
             const selectable = this.selectablePageRows();
             if (selectable.length === 0) {
                 return false;
             }
             return selectable.every((row) => this.isRowSelected(row));
         },
         toggleSelectAllPage() {
             const selectable = this.selectablePageRows();
             if (this.allPageSelected()) {
                 selectable.forEach((row) => delete this.selected[this.selectionKey(row)]);
             } else {
                 selectable.forEach((row) => { this.selected[this.selectionKey(row)] = row; });
             }
         },
         appendAwbRowsToForm(form) {
             form.querySelectorAll('[data-awb-dynamic]').forEach((el) => el.remove());
             const rows = Object.values(this.selected);
             if (rows.length === 0) {
                 alert('Pilih minimal satu baris order.');
                 return false;
             }
             rows.forEach((row, index) => {
                 ['channel', 'order_ref', 'shop_ref'].forEach((field) => {
                     const input = document.createElement('input');
                     input.type = 'hidden';
                     input.name = 'rows[' + index + '][' + field + ']';
                     input.value = row[field] ?? '';
                     input.setAttribute('data-awb-dynamic', '1');
                     form.appendChild(input);
                 });
             });
             return true;
         },
         submitAwbRequest(e) {
             e.preventDefault();
             const form = this.$refs.awbRequestForm;
             if (!form || !this.appendAwbRowsToForm(form)) {
                 return;
             }
             this.awbRequesting = true;
             form.submit();
         },
         submitAwbDownload(e) {
             e.preventDefault();
             const form = this.$refs.awbDownloadForm;
             if (!form || !this.appendAwbRowsToForm(form)) {
                 return;
             }
             const rows = Object.values(this.selected);
             const notReady = rows.filter((row) => ['shopee', 'tiktok'].includes(row.channel) && !row.awb_ready);
             if (notReady.length > 0) {
                 const ok = confirm(
                     notReady.length + ' order belum AWB siap — unduhan akan dilewati untuk yang belum siap. Lanjut?',
                 );
                 if (!ok) {
                     return;
                 }
             }
             this.awbPrinting = true;
             form.submit();
         },
         async toggleDetail(channel, orderRef, url) {
             const key = this.rowKey(channel, orderRef);
             if (this.expandedKey === key) {
                 this.expandedKey = null;
                 return;
             }
             this.expandedKey = key;
             if (this.detailHtml[key]) {
                 return;
             }
             this.loadingKey = key;
             try {
                 const res = await fetch(url, {
                     headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                 });
                 if (!res.ok) {
                     this.detailHtml[key] = '<p class=\'p-3 text-xs text-red-600\'>Gagal memuat detail.</p>';
                     return;
                 }
                 this.detailHtml[key] = await res.text();
             } catch (e) {
                 this.detailHtml[key] = '<p class=\'p-3 text-xs text-red-600\'>Gagal memuat detail.</p>';
             } finally {
                 this.loadingKey = null;
             }
         },
         isExpanded(channel, orderRef) {
             return this.expandedKey === this.rowKey(channel, orderRef);
         },
         isLoading(channel, orderRef) {
             return this.loadingKey === this.rowKey(channel, orderRef);
         },
         detailFor(channel, orderRef) {
             return this.detailHtml[this.rowKey(channel, orderRef)] ?? '';
         },
         toggleRow(id) {
             this.expandedId = this.expandedId === id ? null : id;
         },
         isRowExpanded(id) {
             return this.expandedId === id;
         },
         warehouseLabel(orderId) {
             const key = String(orderId);
             return this.warehouseById[key] ?? this.warehouseById[orderId] ?? null;
         },
         warehouseIdMatches(storedId, orderId) {
             return Number(storedId) === Number(orderId);
         },
         warehousePending(orderId) {
             return this.warehouseLoadingIds.some((id) => this.warehouseIdMatches(id, orderId));
         },
         clearWarehouseLoadingForIds(batchIds) {
             const requested = new Set(batchIds.map((id) => String(id)));
             this.warehouseLoadingIds = this.warehouseLoadingIds.filter((id) => !requested.has(String(id)));
         },
         warehouseAriaName(orderId) {
             const row = this.warehouseLabel(orderId);
             return row && row.aria_name ? row.aria_name : null;
         },
         warehouseUnmapped(orderId) {
             const row = this.warehouseLabel(orderId);
             return row ? !!row.unmapped : false;
         },
         warehousePlatformWh(orderId) {
             const row = this.warehouseLabel(orderId);
             return row && row.platform_wh ? row.platform_wh : null;
         },
         async hydrateWarehouseLabels() {
             if (@js($isEventsView) || this.warehouseLoadingIds.length === 0 || !this.warehouseLabelsUrl) {
                 return;
             }
             if (this.warehouseLabelsFetchInFlight) {
                 return;
             }
             const ids = this.warehouseLoadingIds.slice();
             this.warehouseLabelsFetchInFlight = true;
             try {
                 const res = await fetch(this.warehouseLabelsUrl, {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'Accept': 'application/json',
                         'X-Requested-With': 'XMLHttpRequest',
                         'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '',
                     },
                     body: JSON.stringify({ ids }),
                 });
                 if (!res.ok) {
                     return;
                 }
                 const data = await res.json();
                 if (!data.labels || typeof data.labels !== 'object') {
                     return;
                 }
                 for (const [id, label] of Object.entries(data.labels)) {
                     this.warehouseById[String(id)] = label;
                 }
                 this.clearWarehouseLoadingForIds(ids);
             } catch (e) {
                 /* keep spinners; maintainer can refresh */
             } finally {
                 this.warehouseLabelsFetchInFlight = false;
             }
         }
     }"
     @if(! $isEventsView) x-init="hydrateWarehouseLabels()" @endif>
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-600">
            Read-only log dari unified storage
            (<code class="rounded bg-gray-100 px-1 text-xs">marketplace_webhook_events</code>
            / <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>).
            @if($isEventsView)
            Semua webhook termasuk non-order; tidak mem-post order atau memanggil API marketplace pada render daftar.
            @else
            Ringkasan per order; detail (shadow, webhook, ingress) dimuat lazily — tanpa API Shopee/TikTok/Jubelio pada render awal.
            @endif
        </p>
        @if($isEventsView)
        <span class="mt-3 block max-w-3xl rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" data-testid="omnichannel-events-current-orders-callout">
            Untuk detail per order (expand baris, shadow compare, dan baris SKU × qty TikTok API), buka
            <a href="{{ $currentOrdersUrl }}" class="font-semibold text-blue-800 underline hover:text-blue-950">Current orders (detail baris &amp; API TikTok)</a>
            — filter saat ini tetap dipakai.
        </span>
        @endif
    </div>

    @include('omnichannel.partials.orders-filters', [
        'filters' => $filters,
        'statusOptions' => $statusOptions,
        'warehouseOptions' => $warehouseOptions,
        'shopOptions' => $shopOptions,
        'showShopFilter' => $showShopFilter ?? false,
        'resetQuery' => $resetQuery,
        'defaultOpen' => $hasActiveFilters,
    ])

    @if(! $isEventsView && $opsBucketService !== null && ($ordersTableExists ?? true))
    @php
        $opsCardPresets = [
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_ALL, 'border' => 'border-gray-400', 'bg' => 'bg-gray-50 text-gray-700', 'ring' => 'ring-2 ring-gray-500'],
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED, 'border' => 'border-blue-500', 'bg' => 'bg-blue-50 text-blue-700', 'ring' => 'ring-2 ring-blue-500'],
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_READY_TO_SHIP, 'border' => 'border-indigo-500', 'bg' => 'bg-indigo-50 text-indigo-700', 'ring' => 'ring-2 ring-indigo-500'],
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_IN_TRANSIT, 'border' => 'border-violet-500', 'bg' => 'bg-violet-50 text-violet-800', 'ring' => 'ring-2 ring-violet-500'],
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_COMPLETED, 'border' => 'border-green-500', 'bg' => 'bg-green-50 text-green-700', 'ring' => 'ring-2 ring-green-500'],
            ['bucket' => \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_CANCELLED, 'border' => 'border-red-500', 'bg' => 'bg-red-50 text-red-700', 'ring' => 'ring-2 ring-red-500'],
        ];
        $opsCardQueryBase = array_filter(
            array_merge(request()->query(), ['view' => 'orders']),
            fn ($v) => $v !== null && $v !== '',
        );
        unset($opsCardQueryBase['ops_bucket']);
    @endphp
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 md:gap-4" data-testid="omnichannel-ops-bucket-cards">
        @foreach($opsCardPresets as $card)
        @php
            $bucket = $card['bucket'];
            $isActive = $activeOpsBucket === $bucket
                || ($bucket === \App\Services\Marketplace\MarketplaceChannelOrderOpsBucket::BUCKET_UNPROCESSED
                    && ! request()->query->has('ops_bucket'));
            $cardHref = route('omnichannel.orders.index', array_merge($opsCardQueryBase, ['ops_bucket' => $bucket]));
        @endphp
        <a href="{{ $cardHref }}"
           data-testid="omnichannel-ops-bucket-{{ $bucket }}"
           class="flex items-center justify-between rounded-lg border-l-4 {{ $card['border'] }} {{ $card['bg'] }} p-3 shadow-sm transition-all hover:opacity-90 md:p-4 {{ $isActive ? $card['ring'] : '' }}">
            <div>
                <p class="text-[10px] font-semibold tracking-wider uppercase opacity-70 md:text-xs">{{ $opsBucketService->cardTitle($bucket) }}</p>
                <p class="text-xl font-bold md:text-2xl">{{ format_amount($opsBucketStats[$bucket] ?? 0, 0) }}</p>
            </div>
        </a>
        @endforeach
    </div>
    @endif

    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="omnichannel-awb-flash-error">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="omnichannel-awb-flash-warning">{{ session('warning') }}</div>
    @endif
    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900" data-testid="omnichannel-awb-flash-success">{{ session('success') }}</div>
    @endif

    @php
    $missingTable = $isEventsView ? ! $eventsTableExists : ! $ordersTableExists;
    @endphp

    @if($missingTable)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900" data-testid="omnichannel-orders-missing-migration">
        <p class="font-medium">Tabel belum ada</p>
        <p class="mt-1">Jalankan migrasi unified storage:</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_21_150000_create_marketplace_webhook_events_table.php
php artisan migrate --path=database/migrations/2026_11_21_151000_create_marketplace_channel_orders_table.php</pre>
    </div>
    @elseif($rows === null || $rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" data-testid="omnichannel-orders-empty">
        Tidak ada data untuk filter ini.
        @if($isEventsView)
        Aktifkan <code>MARKETPLACE_WEBHOOK_UNIFIED_STORAGE</code> atau jalankan
        <code class="text-xs">php artisan marketplace:backfill-webhook-events</code>.
        @endif
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        @if(! $isEventsView)
        @php
            require resource_path('views/partials/form-control-class-vars.php');
        @endphp
        <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 space-y-2" data-testid="omnichannel-awb-toolbar">
            <p class="text-xs text-gray-600">
                <span class="font-medium text-gray-800">AWB marketplace (2 langkah)</span>
                — <strong>Minta AWB</strong> lalu <strong>Unduh AWB</strong> (PDF). Shopee + TikTok/Tokopedia.
                <span class="text-gray-500" x-show="selectedCount() > 0" x-cloak>
                    · <span x-text="selectedCount()"></span> terpilih
                </span>
            </p>
            <div class="flex flex-wrap gap-2">
                <form method="post"
                      action="{{ route('omnichannel.orders.shipping-documents.request') }}"
                      x-ref="awbRequestForm">
                    @csrf
                    <button type="button"
                            @click="submitAwbRequest($event)"
                            class="{{ $formControlClassSm }} border-indigo-600 bg-indigo-600 px-3 font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="omnichannel-awb-request-button"
                            :disabled="selectedCount() === 0 || awbRequesting">
                        <span x-show="awbRequesting" x-cloak>Memproses…</span>
                        <span x-show="!awbRequesting" x-cloak>Minta AWB</span>
                    </button>
                </form>
                <form method="post"
                      action="{{ route('omnichannel.orders.shipping-documents') }}"
                      x-ref="awbDownloadForm">
                    @csrf
                    <button type="button"
                            @click="submitAwbDownload($event)"
                            class="{{ $formControlClassSm }} border-blue-600 bg-blue-600 px-3 font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="omnichannel-awb-download-button"
                            :disabled="selectedCount() === 0 || awbPrinting">
                        <span x-show="awbPrinting" x-cloak>Mengunduh…</span>
                        <span x-show="!awbPrinting" x-cloak>Unduh AWB</span>
                    </button>
                </form>
            </div>
        </div>
        @endif
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} baris — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            · {{ $isEventsView ? 'marketplace_webhook_events' : 'marketplace_channel_orders' }}
        </div>
        <div class="min-w-0 overflow-x-auto">
            @if($isEventsView)
            <table class="w-full min-w-[52rem] text-left" data-testid="omnichannel-events-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-36">received_at</th>
                        <th class="{{ $th }} w-24">channel</th>
                        <th class="{{ $th }} w-16">event</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-28">order_status</th>
                        @if($showShopColumn)
                        <th class="{{ $th }} w-32">Shop</th>
                        @endif
                        <th class="{{ $th }} w-36">Aria warehouse</th>
                        <th class="{{ $th }} w-32">Platform WH</th>
                        <th class="{{ $th }} w-20 text-center">sign OK</th>
                        <th class="{{ $th }} w-24 text-right">payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $gudangName = $shopResolver->warehouseNameForListRow($row, $shopRefToWarehouse, $warehouseNames);
                        $gudangUnmapped = filled($row->shop_ref) && $gudangName === null;
                        $platformWhLabel = $shopResolver->platformWarehouseLabelForListRow($row);
                        $payloadJson = json_encode($row->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $payloadPreview = \Illuminate\Support\Str::limit($payloadJson, 80, '…');
                        $channelLabel = $omnichannelChannelLabel($row);
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }}">{{ $channelLabel }}</td>
                        <td class="{{ $td }} text-[11px] text-gray-700">{{ $statusPresenter->eventTypeLabel($row->channel, $row->event_type) }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref ?? '—' }}</td>
                        <td class="{{ $td }}">
                            @php
                                $displayStatus = $statusPresenter->displayStatus($row->channel, $row->order_status);
                                $statusBucket = $statusPresenter->normalizeBucket($row->channel, $row->commerce_platform ?? null, $row->order_status);
                                $bucketLabel = $statusPresenter->bucketChipLabel($statusBucket);
                            @endphp
                            <span class="text-gray-800">{{ $displayStatus }}</span>
                            @if($bucketLabel)
                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-gray-500">{{ $bucketLabel }}</span>
                            @endif
                        </td>
                        @if($showShopColumn)
                        <td class="{{ $td }} font-mono text-[11px] text-gray-600 break-all"
                            data-testid="omnichannel-event-shop-ref-{{ $row->id }}">
                            {{ filled($row->shop_ref) ? $row->shop_ref : '—' }}
                        </td>
                        @endif
                        <td class="{{ $td }} text-gray-800"
                            data-testid="omnichannel-event-gudang-{{ $row->id }}">
                            @if($gudangName)
                            <span class="font-medium">{{ $gudangName }}</span>
                            @elseif($gudangUnmapped)
                            <span title="Not mapped to an Aria warehouse">—</span>
                            <span class="block text-[10px] text-gray-400">Not mapped</span>
                            @else
                            —
                            @endif
                        </td>
                        <td class="{{ $td }} text-gray-600"
                            data-testid="omnichannel-event-platform-wh-{{ $row->id }}">
                            @if($platformWhLabel)
                            <span class="text-xs">{{ $platformWhLabel }}</span>
                            @else
                            —
                            @endif
                        </td>
                        <td class="{{ $td }} text-center">
                            @if($row->signature_ok)
                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">ya</span>
                            @else
                            <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">tidak</span>
                            @endif
                        </td>
                        <td class="{{ $td }} text-right">
                            <button type="button"
                                    @click="toggleRow(@js($row->id))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="omnichannel-event-expand-{{ $row->id }}"
                                    x-text="isRowExpanded(@js($row->id)) ? 'Tutup' : 'Lihat'">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr x-show="isRowExpanded(@js($row->id))" x-cloak class="bg-gray-50">
                        <td colspan="{{ $eventsTableColspan }}" class="px-3 py-3">
                            @if(filled($row->order_ref))
                            @php
                                $eventToOrdersUrl = route('omnichannel.orders.index', array_merge(request()->query(), [
                                    'view' => 'orders',
                                    'order_ref' => $row->order_ref,
                                ]));
                            @endphp
                            <p class="mb-2 text-[11px] text-gray-600" data-testid="omnichannel-event-current-orders-hint">
                                @if($row->channel === 'tiktok')
                                Baris order (SKU × qty) dari TikTok API:
                                @else
                                Detail per order (expand baris):
                                @endif
                                <a href="{{ $eventToOrdersUrl }}" class="font-medium text-blue-700 underline">Current orders</a>
                                @if($row->channel === 'tiktok')
                                untuk order <span class="font-mono">{{ $row->order_ref }}</span>.
                                @else
                                — filter order <span class="font-mono">{{ $row->order_ref }}</span>.
                                @endif
                            </p>
                            @endif
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Raw payload (read-only)</p>
                            <p class="mb-2 font-mono text-[11px] text-gray-500 lg:hidden">{{ $payloadPreview }}</p>
                            <pre class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white p-3 font-mono text-[11px] leading-relaxed text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($row->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <table class="w-full min-w-[50rem] text-left" data-testid="omnichannel-orders-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-10 text-center" data-testid="omnichannel-awb-select-all">
                            <input type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   :checked="allPageSelected()"
                                   @change="toggleSelectAllPage()"
                                   aria-label="Pilih semua di halaman ini">
                        </th>
                        <th class="{{ $th }} w-12"></th>
                        <th class="{{ $th }} w-24">channel</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-28">status</th>
                        <th class="{{ $th }} w-32">AWB</th>
                        @if($showShopColumn)
                        <th class="{{ $th }} w-32">Shop</th>
                        @endif
                        <th class="{{ $th }} w-36">Aria warehouse</th>
                        <th class="{{ $th }} w-32">Platform WH</th>
                        <th class="{{ $th }} w-36" title="Tanggal order di marketplace (bukan waktu webhook Aria)">Tgl order</th>
                        <th class="{{ $th }} w-36">last_event</th>
                        <th class="{{ $th }} w-36">first_received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $gudangName = $shopResolver->warehouseNameForListRow($row, $shopRefToWarehouse, $warehouseNames);
                        $gudangUnmapped = filled($row->shop_ref) && $gudangName === null;
                        $platformWhLabel = $shopResolver->platformWarehouseLabelForListRow($row);
                        $detailUrl = route('omnichannel.orders.show', ['channel' => $row->channel, 'orderRef' => $row->order_ref]);
                        $channelLabel = $omnichannelChannelLabel($row);
                        $awbRow = [
                            'channel' => (string) $row->channel,
                            'order_ref' => (string) $row->order_ref,
                            'shop_ref' => (string) ($row->shop_ref ?? ''),
                            'awb_printed' => filled($row->awb_printed_at ?? null),
                            'awb_ready' => filled($row->awb_document_ready_at ?? null),
                            'awb_pending' => filled($row->awb_document_requested_at ?? null)
                                && blank($row->awb_document_ready_at ?? null),
                        ];
                        $awbAlreadyPrinted = filled($row->awb_printed_at ?? null);
                        $awbDocumentReady = filled($row->awb_document_ready_at ?? null);
                        $awbDocumentPending = filled($row->awb_document_requested_at ?? null) && ! $awbDocumentReady;
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} text-center">
                            <input type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40"
                                   data-testid="omnichannel-awb-row-{{ $row->id }}"
                                   :checked="isRowSelected(@js($awbRow))"
                                   @change="toggleRowSelection(@js($awbRow))"
                                   x-bind:disabled="!canReprintAwb && @js($awbAlreadyPrinted)"
                                   @if($awbAlreadyPrinted && !($canReprintAwb ?? false))
                                   title="AWB sudah dicetak — hubungi admin untuk cetak ulang"
                                   @endif
                                   aria-label="Pilih order {{ $row->order_ref }}">
                        </td>
                        <td class="{{ $td }}">
                            <button type="button"
                                    @click="toggleDetail(@js($row->channel), @js($row->order_ref), @js($detailUrl))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="omnichannel-order-expand-{{ $row->id }}">
                                <span x-text="isExpanded(@js($row->channel), @js($row->order_ref)) ? 'Tutup' : 'Detail'">Detail</span>
                            </button>
                        </td>
                        <td class="{{ $td }}">{{ $channelLabel }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref }}</td>
                        <td class="{{ $td }}">
                            @php
                                $orderDisplayStatus = $statusPresenter->displayStatus($row->channel, $row->order_status);
                                $orderOpsBucket = $opsBucketService?->opsBucketForOrderStatus(
                                    (string) $row->channel,
                                    $row->commerce_platform ?? null,
                                    $row->order_status,
                                    filled($row->awb_printed_at ?? null),
                                );
                                $orderOpsBucketLabel = $orderOpsBucket
                                    ? $opsBucketService->cardTitle($orderOpsBucket)
                                    : null;
                            @endphp
                            <span class="text-gray-800">{{ $orderDisplayStatus }}</span>
                            @if($orderOpsBucketLabel)
                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-gray-500"
                                  title="Ops queue bucket">{{ $orderOpsBucketLabel }}</span>
                            @endif
                        </td>
                        <td class="{{ $td }} text-[11px] text-gray-600"
                            data-testid="omnichannel-order-awb-status-{{ $row->id }}">
                            @if((int) ($row->awb_printed_by ?? 0) > 0)
                            <span class="inline-flex rounded-full bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-900"
                                  title="AWB diunduh {{ $row->awb_printed_at?->format('Y-m-d H:i:s') ?? '—' }}">
                                Dicetak · {{ $row->awbPrintedBy?->username ?? 'user #'.$row->awb_printed_by }}
                            </span>
                            @elseif($awbDocumentReady)
                            <span class="inline-flex rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-900"
                                  data-testid="omnichannel-awb-badge-ready"
                                  title="Siap unduh {{ $row->awb_document_ready_at?->format('Y-m-d H:i:s') ?? '—' }}">
                                AWB ready
                            </span>
                            @elseif($awbDocumentPending)
                            <span class="inline-flex rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-900"
                                  data-testid="omnichannel-awb-badge-pending"
                                  title="Diminta {{ $row->awb_document_requested_at?->format('Y-m-d H:i:s') ?? '—' }}">
                                Menunggu label
                            </span>
                            @else
                            —
                            @endif
                        </td>
                        @if($showShopColumn)
                        <td class="{{ $td }} font-mono text-[11px] text-gray-600 break-all"
                            data-testid="omnichannel-order-shop-ref-{{ $row->id }}">
                            {{ filled($row->shop_ref) ? $row->shop_ref : '—' }}
                        </td>
                        @endif
                        <td class="{{ $td }} text-gray-800"
                            data-testid="omnichannel-order-gudang-{{ $row->id }}">
                            @if($gudangName)
                            <span class="font-medium">{{ $gudangName }}</span>
                            @else
                            <span x-show="warehousePending(@js($row->id))"
                                  x-cloak
                                  class="inline-flex items-center gap-1 text-gray-400"
                                  data-testid="omnichannel-order-gudang-loading-{{ $row->id }}">
                                <svg class="h-3.5 w-3.5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-[10px]">Loading…</span>
                            </span>
                            <span x-show="!warehousePending(@js($row->id)) && warehouseAriaName(@js($row->id))"
                                  x-cloak
                                  class="font-medium"
                                  x-text="warehouseAriaName(@js($row->id))"></span>
                            <span x-show="!warehousePending(@js($row->id)) && warehouseUnmapped(@js($row->id))" x-cloak>
                                <span title="Not mapped to an Aria warehouse">—</span>
                                <span class="block text-[10px] text-gray-400">Not mapped</span>
                            </span>
                            <span x-show="!warehousePending(@js($row->id)) && !warehouseAriaName(@js($row->id)) && !warehouseUnmapped(@js($row->id))" x-cloak class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="{{ $td }} text-gray-600"
                            data-testid="omnichannel-order-platform-wh-{{ $row->id }}">
                            @if($platformWhLabel)
                            <span class="text-xs">{{ $platformWhLabel }}</span>
                            @else
                            <span x-show="warehousePending(@js($row->id))"
                                  x-cloak
                                  class="inline-flex items-center gap-1 text-gray-400"
                                  data-testid="omnichannel-order-platform-wh-loading-{{ $row->id }}">
                                <svg class="h-3.5 w-3.5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span x-show="!warehousePending(@js($row->id)) && warehousePlatformWh(@js($row->id))"
                                  x-cloak
                                  class="text-xs"
                                  x-text="warehousePlatformWh(@js($row->id))"></span>
                            <span x-show="!warehousePending(@js($row->id)) && !warehousePlatformWh(@js($row->id))" x-cloak class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600"
                            data-testid="omnichannel-order-ordered-at-{{ $row->id }}">
                            {{ $row->marketplace_ordered_at?->format('Y-m-d H:i:s') ?? '—' }}
                        </td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->last_event_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->first_received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr x-show="isExpanded(@js($row->channel), @js($row->order_ref))" x-cloak class="bg-gray-50">
                        <td colspan="{{ $ordersTableColspan }}" class="p-0">
                            <template x-if="isLoading(@js($row->channel), @js($row->order_ref))">
                                <p class="p-4 text-xs text-gray-500">Memuat detail…</p>
                            </template>
                            <div x-show="!isLoading(@js($row->channel), @js($row->order_ref))"
                                 x-html="detailFor(@js($row->channel), @js($row->order_ref))"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @if($rows->hasPages())
        <div class="border-t border-gray-100 px-3 py-3">
            {{ $rows->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection